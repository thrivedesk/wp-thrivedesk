<?php
/**
 * Smoke tests for the ThriveDesk plugin loading correctly inside WordPress.
 */
class TD_Plugin_Loads_Test extends WP_UnitTestCase {

    public function test_plugin_is_loaded() {
        $this->assertTrue(
            defined('THRIVEDESK_VERSION'),
            'THRIVEDESK_VERSION should be defined once the plugin file loads.'
        );
    }

    public function test_assistant_url_constant_is_defined_and_overridable() {
        $this->assertTrue(
            defined('THRIVEDESK_ASSISTANT_URL'),
            'THRIVEDESK_ASSISTANT_URL should be defined (the bootloader host constant).'
        );
        $this->assertStringStartsWith(
            'http',
            THRIVEDESK_ASSISTANT_URL,
            'THRIVEDESK_ASSISTANT_URL should be an absolute http(s) URL.'
        );
    }

    public function test_conversation_table_constant() {
        $this->assertSame(
            'td_conversations',
            THRIVEDESK_DB_TABLE_CONVERSATION,
            'The conversation table base name should be td_conversations.'
        );
    }

    public function test_settings_option_shape_is_array_when_set() {
        update_option('td_helpdesk_settings', [
            'td_helpdesk_api_key'      => 'token',
            'td_helpdesk_assistant_id' => 'c0ffee00-0000-4000-8000-000000000001',
            'td_helpdesk_inbox_id'     => 'inbox-uuid',
        ]);
        $settings = get_option('td_helpdesk_settings');
        $this->assertIsArray($settings);
        $this->assertSame('token', $settings['td_helpdesk_api_key']);
    }
}
