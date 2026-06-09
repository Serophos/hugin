<?php $title = __('accessibility.title', [], 'Accessibility statement'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head">
    <div>
        <h1><?= e(__('accessibility.heading', [], 'Accessibility statement')) ?></h1>
        <p class="muted"><?= e(__('accessibility.intro', [], 'This statement documents the accessibility target and feedback channels for the Hugin admin backend.')) ?></p>
    </div>
</div>

<section class="card accessibility-statement" aria-labelledby="accessibility-target">
    <h2 id="accessibility-target"><?= e(__('accessibility.target_heading', [], 'Compliance target')) ?></h2>
    <p><?= e(__('accessibility.target_body', [], 'The Hugin admin backend is designed to support Directive (EU) 2016/2102 using EN 301 549 v3.2.1 and WCAG 2.1 Level AA as the technical baseline. WCAG 2.2 AA best practices are applied where they do not conflict with the harmonised standard.')) ?></p>
    <dl class="meta-list">
        <div><dt><?= e(__('accessibility.review_date', [], 'Statement review date')) ?></dt><dd><?= e($reviewDate) ?></dd></div>
        <div><dt><?= e(__('settings.accessibility_visual_mode', [], 'Admin visual mode')) ?></dt><dd><?= e(__('settings.accessibility_visual_' . str_replace('-', '_', (string)$settings['visual_mode']), [], (string)$settings['visual_mode'])) ?></dd></div>
        <div><dt><?= e(__('settings.accessibility_focus_style', [], 'Focus indicator')) ?></dt><dd><?= e(__('settings.accessibility_focus_' . str_replace('-', '_', (string)$settings['focus_style']), [], (string)$settings['focus_style'])) ?></dd></div>
        <div><dt><?= e(__('settings.accessibility_motion', [], 'Motion')) ?></dt><dd><?= e(__('settings.accessibility_motion_' . str_replace('-', '_', (string)$settings['motion']), [], (string)$settings['motion'])) ?></dd></div>
    </dl>
</section>

<section class="card accessibility-statement" aria-labelledby="accessibility-status">
    <h2 id="accessibility-status"><?= e(__('accessibility.status_heading', [], 'Current status')) ?></h2>
    <p><?= e(__('accessibility.status_body', [], 'The admin backend includes accessible landmarks, keyboard-operable controls, visible focus indicators, announced validation errors, reduced-motion handling, and configurable compliant visual modes. A formal external audit may still identify deployment-specific content or configuration issues.')) ?></p>
    <h3><?= e(__('accessibility.known_limitations_heading', [], 'Known limitations')) ?></h3>
    <p><?= e(__('accessibility.known_limitations_body', [], 'Uploaded media, custom slide content, third-party plugin settings, and operator-provided links may require separate review by the deploying organisation.')) ?></p>
</section>

<section class="card accessibility-statement" aria-labelledby="accessibility-feedback">
    <h2 id="accessibility-feedback"><?= e(__('accessibility.feedback_heading', [], 'Feedback and enforcement')) ?></h2>
    <?php if ($settings['contact_email'] !== ''): ?>
        <p><?= e(__('accessibility.contact_email_text', [], 'Report accessibility barriers by email:')) ?> <a href="mailto:<?= e($settings['contact_email']) ?>"><?= e($settings['contact_email']) ?></a></p>
    <?php else: ?>
        <p class="alert warning"><?= e(__('accessibility.contact_missing', [], 'No accessibility contact email has been configured yet. Add one in Global settings before publishing this statement.')) ?></p>
    <?php endif; ?>
    <?php if ($settings['feedback_url'] !== ''): ?>
        <p><a class="button button--normal" href="<?= e($settings['feedback_url']) ?>" target="_blank" rel="noopener noreferrer"><?= admin_icon('open') ?><span><?= e(__('accessibility.feedback_link', [], 'Open feedback form')) ?></span></a></p>
    <?php endif; ?>
    <?php if ($settings['enforcement_url'] !== ''): ?>
        <p><?= e(__('accessibility.enforcement_text', [], 'If feedback is not handled satisfactorily, contact the responsible enforcement body:')) ?> <a href="<?= e($settings['enforcement_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($settings['enforcement_url']) ?></a></p>
    <?php else: ?>
        <p class="muted"><?= e(__('accessibility.enforcement_missing', [], 'No enforcement body URL has been configured yet.')) ?></p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
