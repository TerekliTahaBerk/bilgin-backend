<?php

declare(strict_types=1);

namespace App\Shared\Domain\Learner;

/**
 * Seviye testinin bittiğini öğrenci profiline işler.
 *
 * Testin KENDİSİ Learning modülünde: soru seçiyor, puanlıyor, ilerleme
 * yazıyor. Ama "bu öğrenci seviye testini geçti mi" sorusu profile ait ve
 * profil Identity'nin. Learning'in `UserProfile` modeline uzanması, iki
 * modülü birbirine düğümlerdi.
 */
interface PlacementRecorder
{
    /** Testi tamamlanmış işaretler. İkinci çağrı ilk damgayı değiştirmez. */
    public function markCompleted(int $userId): void;

    public function hasCompleted(int $userId): bool;
}
