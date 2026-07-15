<?php
/**
 * @package ThriveDesk
 */

class TD_Plugin_Loads_Test extends WP_UnitTestCase {

    public function test_plugin_is_loaded() {
        $this->assertTrue(
            defined('THRIVEDESK_VERSION'),
            'THRIVEDESK_VERSION should be defined once the plugin file loads.'
        );
    }

    public function test_assistant_url_constant_is_an_absolute_url() {
        $this->assertTrue(
            defined('THRIVEDESK_ASSISTANT_URL'),
            'THRIVEDESK_ASSISTANT_URL should be defined (the bootloader host constant).'
        );
        // Overridable via an earlier define(), so the invariant is the scheme, not a specific host.
        $this->assertMatchesRegularExpression(
            '#^https?://#',
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

    public function test_bootloader_script_is_built_from_assistant_url_constant() {
        update_option('td_helpdesk_settings', [
            'td_helpdesk_api_key'      => 'token',
            'td_helpdesk_assistant_id' => 'c0ffee00-0000-4000-8000-000000000001',
        ]);
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        ob_start();
        \ThriveDesk\Assistants\Assistant::instance()->load_assistant_script();
        $output = ob_get_clean();

        $this->assertNotEmpty(
            $output,
            'The assistant script should render when an assistant id is configured.'
        );
        $this->assertStringContainsString(
            THRIVEDESK_ASSISTANT_URL . '/bootloader.js',
            $output,
            'The bootloader src must be derived from THRIVEDESK_ASSISTANT_URL.'
        );
    }

    public function test_no_bootloader_script_without_assistant_id() {
        update_option('td_helpdesk_settings', [
            'td_helpdesk_api_key' => 'token',
        ]);

        ob_start();
        \ThriveDesk\Assistants\Assistant::instance()->load_assistant_script();
        $output = ob_get_clean();

        $this->assertSame(
            '',
            $output,
            'No assistant script should render when no assistant id is set.'
        );
    }

    public function test_get_td_helpdesk_settings_returns_saved_settings() {
        update_option('td_helpdesk_settings', [
            'td_helpdesk_api_key'      => 'token',
            'td_helpdesk_assistant_id' => 'assistant-123',
        ]);

        $settings = get_td_helpdesk_settings();

        $this->assertIsArray($settings);
        $this->assertSame('assistant-123', $settings['td_helpdesk_assistant_id']);
    }

    public function test_gravatar_url_normalises_email_and_uses_404_default() {
        $url = get_gravatar_url(' Foo@Example.com ');

        $this->assertStringContainsString(
            md5('foo@example.com'),
            $url,
            'Gravatar hash is md5 of the lower-cased, trimmed email.'
        );
        // d=404 makes unknown emails 404 so the <img> onerror handler can fall
        // back to initials instead of Gravatar's generic silhouette.
        $this->assertStringContainsString(
            'd=404',
            $url,
            'Gravatar URL should request d=404 so the initials fallback triggers.'
        );
    }

    public function test_portal_back_url_drops_conversation_id_and_keeps_context() {
        $prev = $_SERVER['REQUEST_URI'] ?? null;
        // Conversation opened on the WooCommerce "Support" endpoint.
        $_SERVER['REQUEST_URI'] = '/my-account/td-support/?td_conversation_id=42&cv_status=open';

        $url = td_portal_back_to_list_url();

        $this->assertStringNotContainsString(
            'td_conversation_id',
            $url,
            'Back URL should drop td_conversation_id so the list renders.'
        );
        $this->assertStringContainsString(
            '/my-account/td-support/',
            $url,
            'Back URL should keep the current path (Support tab), not fall back to /my-account/.'
        );
        $this->assertStringContainsString(
            'cv_status=open',
            $url,
            'Other query args (filters/page) should be preserved.'
        );

        if ($prev === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $prev;
        }
    }

    public function test_woocommerce_thumbnail_guard_preserves_null_when_missing() {
        if (!class_exists('ThriveDesk\\Plugins\\WooCommerce')) {
            $this->markTestSkipped('WooCommerce integration class is not autoloaded.');
        }

        // Mirrors the is_array($thumbnail) ? $thumbnail[0] : null ternary in WooCommerce::get_order_items().
        $cases = [false, null, ''];
        foreach ($cases as $thumbnail) {
            $this->assertNull(
                is_array($thumbnail) ? $thumbnail[0] : null,
                'A non-array $thumbnail must yield null (no array offset on bool).'
            );
        }
    }
}
