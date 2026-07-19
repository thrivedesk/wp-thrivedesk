<?php

namespace ThriveDesk\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed, allowlisted view over the raw `extra` payload the SaaS sends for a
 * conversation sync. Decoding untrusted input here means it can never carry an
 * arbitrary column set or values into the $wpdb writes on td_conversations.
 *
 * Immutable by convention: built once via fromExtra(), no setters. (PHP 7.4 has
 * no readonly keyword.)
 */
final class ConversationSyncData {

	/**
	 * Columns a create/replace may write. updated_at / deleted_at are managed
	 * server-side; anything else in the payload is dropped.
	 */
	private const CONVERSATION_COLUMNS = array( 'id', 'title', 'ticket_id', 'inbox_id', 'contact', 'status', 'created_at' );

	private array $conversation_row;
	private array $conversation_ids;
	private string $inbox_id;
	private string $conversation_id;
	private string $status;
	private bool $create_new_contact;
	private string $contact_name;

	private function __construct(
		array $conversation_row,
		array $conversation_ids,
		string $inbox_id,
		string $conversation_id,
		string $status,
		bool $create_new_contact,
		string $contact_name
	) {
		$this->conversation_row   = $conversation_row;
		$this->conversation_ids   = $conversation_ids;
		$this->inbox_id           = $inbox_id;
		$this->conversation_id    = $conversation_id;
		$this->status             = $status;
		$this->create_new_contact = $create_new_contact;
		$this->contact_name       = $contact_name;
	}

	public static function fromExtra( array $extra ): self {
		return new self(
			self::allowlist_conversation( $extra['conversation'] ?? array() ),
			self::string_list( $extra['conversation_ids'] ?? array() ),
			self::text( $extra['inbox_id'] ?? '' ),
			self::text( $extra['conversation_id'] ?? '' ),
			self::text( $extra['status'] ?? '' ),
			! empty( $extra['create_new_contact'] ),
			self::text( $extra['contact_name'] ?? '' )
		);
	}

	public function conversationRow(): array {
		return $this->conversation_row;
	}

	public function conversationIds(): array {
		return $this->conversation_ids;
	}

	public function inboxId(): string {
		return $this->inbox_id;
	}

	public function conversationId(): string {
		return $this->conversation_id;
	}

	public function status(): string {
		return $this->status;
	}

	public function createNewContact(): bool {
		return $this->create_new_contact;
	}

	public function contactName(): string {
		return $this->contact_name;
	}

	/**
	 * Keep only real td_conversations columns; type ticket_id, sanitize the rest.
	 */
	private static function allowlist_conversation( $conversation ): array {
		if ( ! is_array( $conversation ) ) {
			return array();
		}

		$row = array();
		foreach ( self::CONVERSATION_COLUMNS as $column ) {
			if ( ! array_key_exists( $column, $conversation ) ) {
				continue;
			}
			$row[ $column ] = ( 'ticket_id' === $column )
				? (int) $conversation[ $column ]
				: self::text( $conversation[ $column ] );
		}

		return $row;
	}

	private static function string_list( $ids ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$list = array();
		foreach ( array_values( $ids ) as $id ) {
			$list[] = self::text( $id );
		}

		return $list;
	}

	private static function text( $value ): string {
		return sanitize_text_field( (string) $value );
	}
}
