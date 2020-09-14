<?php

namespace ThriveDesk\Api;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class ApiResponse
{
    /**
     * Response status
     *
     * @var boolean
     */
    public $status = true;

    /**
     * Response HTTP code
     *
     * @var int
     */
    public $status_code = null;

    /**
     * Response message
     *
     * @var string
     */
    public $message = '';

    /**
     * Response data
     *
     * @var Array|Object
     */
    public $data = [];

    /**
     * Construct Response class.
     *
     * @since 0.0.1
     * @access public
     */
    public function __construct()
    {
    }

    public function status_code(int $status_code): object
    {
        $this->status_code = $status_code;

        return $this;
    }

    public function message(string $message): object
    {
        $this->message = $message;

        return $this;
    }

    public function data(array $data): object
    {
        $this->data = $data;

        return $this;
    }

    public function error(int $status_code, string $message = '')
    {
        return $this->status_code($status_code)->message($message)->send();
    }

    public function success(int $status_code = 200, array $data = [], string $message = '')
    {
        return $this->status_code($status_code)->data($data)->message($message)->send();
    }

    public function send(): void
    {
        $response = [
            'message' => $this->message
        ];

        if (true === $this->status && $this->data) {
            $response['data'] = $this->data;
        }

        wp_send_json($response, $this->status_code);
    }
}