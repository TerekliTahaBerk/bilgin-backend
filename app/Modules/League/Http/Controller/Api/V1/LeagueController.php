<?php

declare(strict_types=1);

namespace App\Modules\League\Http\Controller\Api\V1;

use App\Modules\League\Application\UseCase\GetStandings;
use App\Modules\League\Application\UseCase\StandingMember;
use App\Modules\League\Application\UseCase\StandingsView;
use App\Modules\League\Infrastructure\Eloquent\Model\LeagueMembership;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeagueController extends ApiController
{
    /** GET /v1/league/current — tasarımdaki "Zümrüt Lig" ekranı. */
    public function current(Request $request, GetStandings $standings): JsonResponse
    {
        $view = $standings($this->userId($request));

        if ($view === null) {
            // Bu hafta hiç XP kazanmamış kullanıcı henüz bir ligde değil.
            // Hata değil: istemci "bir tur yap, lige katıl" ekranını gösterir.
            return ApiResponse::data([
                'joined' => false,
                'message' => 'Bu hafta bir tur tamamladığında lige katılacaksın.',
            ]);
        }

        return ApiResponse::data(self::present($view));
    }

    /** GET /v1/league/history — profil ekranındaki "Lig Geçmişim". */
    public function history(Request $request): JsonResponse
    {
        $history = LeagueMembership::query()
            ->with('league')
            ->where('user_id', $this->userId($request))
            ->whereNotNull('final_rank')
            ->orderByDesc('week_start')
            ->limit(20)
            ->get()
            ->map(fn (LeagueMembership $m): array => [
                'week_start' => $m->week_start->format('Y-m-d'),
                'tier' => $m->league->tier->value,
                'tier_label' => $m->league->tier->label(),
                'rank' => $m->final_rank,
                'weekly_xp' => $m->weekly_xp,
                'result' => $m->result,
            ]);

        return ApiResponse::data($history->all());
    }

    /** @return array<string, mixed> */
    private static function present(StandingsView $view): array
    {
        return array_filter([
            'joined' => true,
            'tier' => $view->tier->value,
            'name' => $view->tier->label(),
            'week_start' => $view->weekStart,
            'ends_at' => $view->endsAt->format(DATE_ATOM),
            'my_rank' => $view->myRank,
            'promotion_zone' => $view->promotionCount,
            'demotion_zone_starts_at' => $view->demotionZoneStartsAt(),
            'xp_to_promotion' => $view->xpToPromotion(),
            'members' => array_map(self::member(...), $view->members),
        ], static fn ($v): bool => $v !== null);
    }

    /** @return array<string, mixed> */
    private static function member(StandingMember $member): array
    {
        return array_filter([
            'rank' => $member->rank,
            'name' => $member->name,
            'avatar_key' => $member->avatarKey,
            'weekly_xp' => $member->weeklyXp,
            'streak' => $member->streak ?: null,
            'is_me' => $member->isMe ?: null,
        ], static fn ($v): bool => $v !== null);
    }
}
