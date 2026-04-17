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

?>
<div class="plugin-screen-meta">
    <div class="plugin-screen-meta__inner">
        <h2><?= e($settings['heading']) ?></h2>
        <?php if ($settings['note'] !== ''): ?>
            <p class="plugin-screen-meta__note"><?= e($settings['note']) ?></p>
        <?php endif; ?>
        <dl>
            <?php foreach ($rows as $label => $value): ?>
                <div>
                    <dt><?= e((string)$label) ?></dt>
                    <dd><?= e((string)$value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </div>
</div>
