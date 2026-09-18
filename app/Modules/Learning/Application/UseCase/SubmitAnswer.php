<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Application\DTO\SubmitAnswerCommand;
use App\Modules\Learning\Domain\Exception\LearningException;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\GraderRegistry;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;
use App\Modules\Learning\Infrastructure\Eloquent\Model\AnswerAttempt;
use App\Modules\Learning\Infrastructure\Eloquent\Model\SessionItem;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Hearts\HeartConsumer;
use Illuminate\Support\Facades\DB;

/**
 * Tek bir cevabı değerlendirir.
 *
 * Değerlendirme SUNUCUDA yapılır; cevap anahtarı istemciye hiç gitmedi.
 * Doğruluk, XP ve can istemciden alınmaz — yalnızca burada üretilir.
 *
 * Idempotency: aynı soru ikinci kez gönderilirse ilk sonuç aynen döner,
 * can tekrar düşmez. Offline'dan dönen istemci bunu tetikler ve bu bir
 * hata değil, beklenen davranıştır.
 */
final readonly class SubmitAnswer
{
    public function __construct(
        private ClockInterface $clock,
        private GraderRegistry $graders,
        private HeartConsumer $hearts,
    ) {}

    public function __invoke(SubmitAnswerCommand $command): AnswerOutcome
    {
        return DB::transaction(function () use ($command): AnswerOutcome {
            $session = $this->activeSession($command);

            /** @var SessionItem|null $item */
            $item = $session->items()->where('exercise_id', $command->exerciseId)->first();

            if ($item === null) {
                throw LearningException::exerciseNotInSession();
            }

            if ($item->isAnswered()) {
                return $this->outcomeFor($session, $item, replayed: true);
            }

            $result = $this->graders->for($item->type)->grade(
                new ExerciseContent(
                    content: $item->content_snapshot,
                    answerKey: $item->answer_key_snapshot,
                    explanation: $item->explanation_snapshot,
                ),
                new SubmittedAnswer($command->answer, $command->elapsedMs),
            );

            $suspicious = $this->isSuspicious($item->type, $command->elapsedMs);

            $item->fill([
                'answered_at' => $this->clock->now(),
                'is_correct' => $result->isCorrect,
                'partial_score' => $result->partialScore,
                'elapsed_ms' => $command->elapsedMs,
                'is_suspicious' => $suspicious,
            ])->save();

            $result->isCorrect ? $session->correct_count++ : $session->wrong_count++;
            $session->save();

            AnswerAttempt::query()->create([
                'user_id' => $command->userId,
                'exercise_id' => $item->exercise_id,
                'topic_id' => $item->topic_id,
                'study_session_id' => $session->id,
                'is_correct' => $result->isCorrect,
                'partial_score' => $result->partialScore,
                'elapsed_ms' => $command->elapsedMs,
                'is_suspicious' => $suspicious,
                'answered_at' => $this->clock->now(),
            ]);

            $hearts = $result->isWrong() && $session->consumes_hearts
                ? $this->hearts->consume($command->userId, "session:{$session->uuid}:ex:{$item->exercise_id}")
                : null;

            return new AnswerOutcome(
                isCorrect: $result->isCorrect,
                partialScore: $result->partialScore,
                correctAnswer: $result->correctAnswer,
                explanation: $item->explanation_snapshot,
                hearts: $hearts,
                answered: $session->answeredCount(),
                total: $session->items()->count(),
                suspicious: $suspicious,
                revealsAnswer: ! $session->isExam(),
            );
        });
    }

    private function activeSession(SubmitAnswerCommand $command): StudySession
    {
        $session = StudySession::query()
            ->where('uuid', $command->sessionUuid)
            ->where('user_id', $command->userId)
            ->lockForUpdate()
            ->first();

        if ($session === null) {
            throw LearningException::sessionNotFound();
        }

        if ($session->status === 'completed') {
            throw LearningException::sessionAlreadyCompleted();
        }

        if (! $session->isActive() || $session->expires_at < $this->clock->now()) {
            throw LearningException::sessionExpired();
        }

        return $session;
    }

    /**
     * İnsan-dışı hızda verilen cevap işaretlenir; XP üretmez.
     *
     * Hesabı KAPATMAZ — yanlış pozitifle öğrenci kaybetmek, birkaç hileciden
     * pahalıdır. İşaret panelde raporlanır, karar insana kalır.
     */
    private function isSuspicious(ExerciseType $type, int $elapsedMs): bool
    {
        if (! config('tekrarla.learning.suspicious_answer_enabled', true)) {
            return false;
        }

        return $elapsedMs > 0 && $elapsedMs < $type->minimumAnswerMs();
    }

    private function outcomeFor(StudySession $session, SessionItem $item, bool $replayed): AnswerOutcome
    {
        return new AnswerOutcome(
            isCorrect: (bool) $item->is_correct,
            partialScore: (float) $item->partial_score,
            correctAnswer: $item->answer_key_snapshot,
            explanation: $item->explanation_snapshot,
            hearts: null,
            answered: $session->answeredCount(),
            total: $session->items()->count(),
            suspicious: $item->is_suspicious,
            idempotentReplay: $replayed,
            revealsAnswer: ! $session->isExam(),
        );
    }
}
