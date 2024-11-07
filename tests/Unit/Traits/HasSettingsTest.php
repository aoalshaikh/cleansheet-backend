<?php

namespace Tests\Unit\Traits;

use App\Traits\HasSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasSettingsTest extends TestCase
{
    use RefreshDatabase;

    private TestModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new TestModel();
    }

    public function test_get_setting(): void
    {
        $this->model->settings = [
            'key1' => 'value1',
            'nested' => [
                'key2' => 'value2',
            ],
        ];

        $this->assertEquals('value1', $this->model->getSetting('key1'));
        $this->assertEquals('value2', $this->model->getSetting('nested.key2'));
        $this->assertEquals('default', $this->model->getSetting('non.existent', 'default'));
    }

    public function test_set_setting(): void
    {
        $this->model->setSetting('key1', 'value1');
        $this->model->setSetting('nested.key2', 'value2');

        $this->assertEquals('value1', $this->model->getSetting('key1'));
        $this->assertEquals('value2', $this->model->getSetting('nested.key2'));
    }

    public function test_remove_setting(): void
    {
        $this->model->settings = [
            'key1' => 'value1',
            'nested' => [
                'key2' => 'value2',
            ],
        ];

        $this->model->removeSetting('key1');
        $this->model->removeSetting('nested.key2');

        $this->assertNull($this->model->getSetting('key1'));
        $this->assertNull($this->model->getSetting('nested.key2'));
    }

    public function test_has_setting(): void
    {
        $this->model->settings = [
            'key1' => 'value1',
            'nested' => [
                'key2' => 'value2',
            ],
        ];

        $this->assertTrue($this->model->hasSetting('key1'));
        $this->assertTrue($this->model->hasSetting('nested.key2'));
        $this->assertFalse($this->model->hasSetting('non.existent'));
    }

    public function test_get_all_settings(): void
    {
        $settings = [
            'key1' => 'value1',
            'nested' => [
                'key2' => 'value2',
            ],
        ];

        $this->model->settings = $settings;

        $this->assertEquals($settings, $this->model->getAllSettings());
    }

    public function test_set_settings(): void
    {
        $settings1 = ['key1' => 'value1'];
        $settings2 = ['key2' => 'value2'];

        // Test without merge
        $this->model->setSettings($settings1, false);
        $this->assertEquals($settings1, $this->model->getAllSettings());

        // Test with merge
        $this->model->setSettings($settings2, true);
        $this->assertEquals(
            array_merge($settings1, $settings2),
            $this->model->getAllSettings()
        );
    }

    public function test_remove_settings(): void
    {
        $this->model->settings = [
            'key1' => 'value1',
            'key2' => 'value2',
            'nested' => [
                'key3' => 'value3',
            ],
        ];

        $this->model->removeSettings(['key1', 'nested.key3']);

        $this->assertNull($this->model->getSetting('key1'));
        $this->assertEquals('value2', $this->model->getSetting('key2'));
        $this->assertNull($this->model->getSetting('nested.key3'));
    }

    public function test_clear_settings(): void
    {
        $this->model->settings = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $this->model->clearSettings();

        $this->assertEquals([], $this->model->getAllSettings());
    }

    public function test_get_settings_group(): void
    {
        $this->model->settings = [
            'group1' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ];

        $this->assertEquals(
            ['key1' => 'value1', 'key2' => 'value2'],
            $this->model->getSettingsGroup('group1')
        );
        $this->assertEquals([], $this->model->getSettingsGroup('non_existent_group'));
    }

    public function test_set_settings_group(): void
    {
        $group = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        // Test without merge
        $this->model->setSettingsGroup('group1', $group, false);
        $this->assertEquals($group, $this->model->getSettingsGroup('group1'));

        // Test with merge
        $this->model->setSettingsGroup('group1', ['key3' => 'value3'], true);
        $this->assertEquals(
            array_merge($group, ['key3' => 'value3']),
            $this->model->getSettingsGroup('group1')
        );
    }

    public function test_remove_settings_group(): void
    {
        $this->model->settings = [
            'group1' => [
                'key1' => 'value1',
            ],
            'group2' => [
                'key2' => 'value2',
            ],
        ];

        $this->model->removeSettingsGroup('group1');

        $this->assertEquals([], $this->model->getSettingsGroup('group1'));
        $this->assertNotEmpty($this->model->getSettingsGroup('group2'));
    }

    public function test_initialize_settings(): void
    {
        $this->model->settings = [
            'custom' => 'value',
        ];

        $this->model->initializeSettings();

        $this->assertEquals(
            array_merge(
                $this->model->getDefaultSettings(),
                ['custom' => 'value']
            ),
            $this->model->getAllSettings()
        );
    }
}

class TestModel extends Model
{
    use HasSettings;

    protected $fillable = ['settings'];
    protected $casts = ['settings' => 'array'];

    protected $defaultSettings = [
        'default1' => 'value1',
        'default2' => 'value2',
    ];

    // Prevent actual database operations in the test
    public function save(array $options = []): bool
    {
        return true;
    }
}
