<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu\Setup;

use RuntimeException;

final class ConfigWriter
{
    public function __construct(private readonly string $configFile)
    {
    }

    /** @param array<string, mixed> $config @return array{config_file:string,backup_file:?string} */
    public function write(array $config): array
    {
        $this->assertGeneratedConfig($config);
        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.config_write_failed'));
        }

        $backup = null;
        if (is_file($this->configFile)) {
            $backup = $this->configFile . '.' . date('YmdHis') . '.bak';
            if (!copy($this->configFile, $backup)) {
                throw new RuntimeException(__('plugins.tl-1menu.errors.config_backup_failed'));
            }
        }

        $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        $tmp = $this->configFile . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $php, LOCK_EX) === false) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.config_write_failed'));
        }

        $lint = trim((string)shell_exec('php -l ' . escapeshellarg($tmp) . ' 2>&1'));
        if (!str_contains($lint, 'No syntax errors detected')) {
            @unlink($tmp);
            throw new RuntimeException(__('plugins.tl-1menu.errors.config_invalid_generated'));
        }

        if (!rename($tmp, $this->configFile)) {
            @unlink($tmp);
            throw new RuntimeException(__('plugins.tl-1menu.errors.config_write_failed'));
        }

        return ['config_file' => $this->configFile, 'backup_file' => $backup];
    }

    /** @param array<string, mixed> $config */
    private function assertGeneratedConfig(array $config): void
    {
        if ((int)($config['schema_version'] ?? 0) !== 2) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.config_invalid_generated'));
        }
        foreach (['field_definitions', 'field_mapping', 'price_groups', 'mensen', 'food_types', 'categories', 'token_catalog'] as $key) {
            if (!is_array($config[$key] ?? null)) {
                throw new RuntimeException(__('plugins.tl-1menu.errors.config_invalid_generated'));
            }
        }
    }
}
