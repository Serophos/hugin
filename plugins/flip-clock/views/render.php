<?php
/**
 * Hugin - Digital Signage System
 * Copyright (C) 2026 Thees Winkler
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Source code: https://github.com/Serophos/hugin
 */

$digitCount = !empty($showSeconds) ? 6 : 4;
?>
<div class="flip-clock-slide" data-flip-clock data-flip-show-seconds="<?= !empty($showSeconds) ? '1' : '0' ?>" style="--flip-clock-background: <?= e($backgroundColor) ?>;">
    <?php if (!empty($backgroundImageUrl)): ?>
        <div class="flip-clock-slide__background" style="background-image: url('<?= e($backgroundImageUrl) ?>');" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="flip-clock" aria-live="polite" aria-label="">
        <?php for ($i = 0; $i < $digitCount; $i++): ?>
            <?php if ($i === 2 || (!empty($showSeconds) && $i === 4)): ?>
                <div class="flip-clock__separator" data-flip-separator aria-hidden="true">:</div>
            <?php endif; ?>
            <div class="flip-clock__digit" data-flip-digit data-value="0" aria-hidden="true">
                <div class="flip-clock__half flip-clock__half--top"><span data-flip-top>0</span></div>
                <div class="flip-clock__half flip-clock__half--bottom"><span data-flip-bottom>0</span></div>
                <div class="flip-clock__fold flip-clock__fold--top"><span data-flip-fold-top>0</span></div>
                <div class="flip-clock__fold flip-clock__fold--bottom"><span data-flip-fold-bottom>0</span></div>
            </div>
        <?php endfor; ?>
    </div>
</div>
