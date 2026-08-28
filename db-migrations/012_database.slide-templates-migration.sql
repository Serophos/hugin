CREATE TABLE IF NOT EXISTS slide_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    landscape_spec_json LONGTEXT NOT NULL,
    portrait_spec_json LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id INT UNSIGNED NULL,
    updated_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_slide_templates_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_slide_templates_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS slide_template_data (
    slide_id INT UNSIGNED PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    values_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_slide_template_data_slide FOREIGN KEY (slide_id) REFERENCES slides(id) ON DELETE CASCADE,
    CONSTRAINT fk_slide_template_data_template FOREIGN KEY (template_id) REFERENCES slide_templates(id) ON DELETE RESTRICT
);
