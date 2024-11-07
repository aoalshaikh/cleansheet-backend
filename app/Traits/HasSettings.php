<?php

namespace App\Traits;

use Illuminate\Support\Arr;

trait HasSettings
{
    /**
     * Get a setting value.
     *
     * @param string $key The dot notation key
     * @param mixed $default The default value if the setting doesn't exist
     * @return mixed
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->settings ?? [], $key, $default);
    }

    /**
     * Set a setting value.
     *
     * @param string $key The dot notation key
     * @param mixed $value The value to set
     * @return void
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        Arr::set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Remove a setting.
     *
     * @param string $key The dot notation key
     * @return void
     */
    public function removeSetting(string $key): void
    {
        $settings = $this->settings ?? [];
        Arr::forget($settings, $key);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Check if a setting exists.
     *
     * @param string $key The dot notation key
     * @return bool
     */
    public function hasSetting(string $key): bool
    {
        return Arr::has($this->settings ?? [], $key);
    }

    /**
     * Get all settings.
     *
     * @return array<string, mixed>
     */
    public function getAllSettings(): array
    {
        return $this->settings ?? [];
    }

    /**
     * Set multiple settings at once.
     *
     * @param array<string, mixed> $settings
     * @param bool $merge Whether to merge with existing settings
     * @return void
     */
    public function setSettings(array $settings, bool $merge = true): void
    {
        if ($merge) {
            $settings = array_merge($this->settings ?? [], $settings);
        }
        
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Remove multiple settings at once.
     *
     * @param array<int, string> $keys
     * @return void
     */
    public function removeSettings(array $keys): void
    {
        $settings = $this->settings ?? [];
        foreach ($keys as $key) {
            Arr::forget($settings, $key);
        }
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Clear all settings.
     *
     * @return void
     */
    public function clearSettings(): void
    {
        $this->settings = [];
        $this->save();
    }

    /**
     * Get settings for a specific group.
     *
     * @param string $group
     * @return array<string, mixed>
     */
    public function getSettingsGroup(string $group): array
    {
        return $this->getSetting($group, []);
    }

    /**
     * Set settings for a specific group.
     *
     * @param string $group
     * @param array<string, mixed> $settings
     * @param bool $merge Whether to merge with existing group settings
     * @return void
     */
    public function setSettingsGroup(string $group, array $settings, bool $merge = true): void
    {
        if ($merge) {
            $existingSettings = $this->getSettingsGroup($group);
            $settings = array_merge($existingSettings, $settings);
        }

        $this->setSetting($group, $settings);
    }

    /**
     * Remove a settings group.
     *
     * @param string $group
     * @return void
     */
    public function removeSettingsGroup(string $group): void
    {
        $this->removeSetting($group);
    }

    /**
     * Get the model's default settings.
     *
     * @return array<string, mixed>
     */
    public function getDefaultSettings(): array
    {
        return $this->defaultSettings ?? [];
    }

    /**
     * Initialize settings with defaults.
     *
     * @return void
     */
    public function initializeSettings(): void
    {
        $this->settings = array_merge(
            $this->getDefaultSettings(),
            $this->settings ?? []
        );
        $this->save();
    }
}
