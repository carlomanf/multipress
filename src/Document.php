<?php

namespace Multipress;

/**
 * Core class for representing documents.
 *
 * @since 1.0.0
 */
final class Document
{
	/**
	 * The database for saving this document.
	 * Set through the constructor.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Database
	 */
	private $database;

	/**
	 * The numeric ID of this document.
	 * Null indicates a new document that is not yet saved to the database.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	private $id = null;

	/**
	 * The domain on which this document originates.
	 * Set through the constructor and is not updatable.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Domain
	 */
	private $origin;

	/**
	 * The user with ownership over this document.
	 * Set through the constructor and is not updatable.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var User
	 */
	private $owner;

	/**
	 * The type of this document.
	 * Set through the constructor and is not updatable.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Document_Type
	 */
	private $type;

	/**
	 * Array of string keys and string values for this document.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private $data = array();

	/**
	 * Getter.
	 * Values of the `$data` array can be accessed by passing the key.
	 *
	 * `id`, `owner`, `origin`, `type` and `data` are reserved words and access their respective fields rather than the `$data` array.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Key.
	 *
	 * @return mixed Value.
	 */
	public function __get( $key )
	{
		if ( $key === 'id' )
		{
			return $this->id;
		}

		if ( $key === 'owner' )
		{
			return $this->owner;
		}

		if ( $key === 'origin' )
		{
			return $this->origin;
		}

		if ( $key === 'type' )
		{
			return $this->type;
		}

		if ( $key === 'data' )
		{
			return $this->data;
		}

		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
	}

	/**
	 * Setter.
	 * Values of the `$data` array can be accessed by passing the key.
	 *
	 * `id`, `owner`, `origin`, `type` and `data` are reserved words and do not save to the `$data` array.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Key.
	 * @param string $value Value.
	 */
	public function __set( string $key, string $value )
	{
		if ( !in_array( $key, array( 'id', 'owner', 'origin', 'type', 'data' ), true ) )
		{
			$this->data[ $key ] = $value;
		}
	}

	/**
	 * Private method that calls the constructor on rows from the database.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 *
	 * @param array[] $rows {
	 *     Database rows, indexed by document ID.
	 *
	 *     @type string $type Document type slug.
	 *     @type string $owner Numeric string representing the document owner ID.
	 *     @type string $origin Numeric string representing the document origin ID.
	 *     @type string[] $data Additional data for this document.
	 * }
	 * @param Environment $environment The environment instance, for validating types and readability.
	 * @param bool $single Whether to construct a document for only the first row or all rows.
	 *
	 * @return Document[]|Document|null An array of valid documents if `$single` is false. If `$single` is true, the method returns either a single document or null if there were no valid documents.
	 */
	private static function construct( array $rows, Environment $environment, bool $single = false )
	{
		$documents = array();

		foreach ( $rows as $id => $row )
		{
			$type = $environment->get_type( (string) $row['type'] );
			$origin = Domain::get_by_id( $environment, (int) $row['origin'] );
			$owner = User::get_by_id( $environment, (int) $row['owner'] );

			if ( !isset( $type ) || !isset( $origin ) || !isset( $owner ) )
			{
				continue;
			}

			$document = new Document( $environment->database, $type, $owner, $origin );
			$document->data = (array) $row['data'];
			$document->id = (int) $id;

			if ( is_null( $environment->current_user ) ? !$document->is_readable_by_public( $environment->current_domain ) : !$document->is_readable( $environment->current_user ) )
			{
				continue;
			}

			if ( $single )
			{
				return $document;
			}

			$documents[ (int) $id ] = $document;
		}

		return $single ? null : $documents;
	}

	/**
	 * Returns documents by type.
	 *
	 * @since 1.0.0
	 *
	 * @param Environment $environment The environment instance, for validating the type.
	 * @param string $type The type slug.
	 *
	 * @return Document[] Array of documents.
	 */
	public static function get_by_type( Environment $environment, string $type ): array
	{
		if ( empty( $cache = $environment->get_from_document_cache( 'type', $type ) ) )
		{
			foreach ( ( $documents = self::construct( $environment->database->get_documents_by_type( $type ), $environment ) ) as $document )
			{
				$environment->save_to_document_cache( 'id', (string) $document->id, $document );
				$environment->save_to_document_cache( 'type', $document->type->slug, $document );
			}

			return $documents;
		}
		else
		{
			return $cache;
		}
	}

	/**
	 * Constructor.
	 * Constructs a new, unsaved document with an ID of `null`.
	 *
	 * @since 1.0.0
	 *
	 * @param Database $database The database to which this document will be saved.
	 * @param Document_Type $type The type of this document.
	 * @param User $owner The user with ownership over this document.
	 * @param Domain $origin The domain on which this document originates.
	 */
	public function __construct( Database $database, Document_Type $type, User $owner, Domain $origin )
	{
		$this->database = $database;
		$this->type = $type;
		$this->origin = $origin;
		$this->owner = $owner;
	}

	/**
	 * Save to the database any changes made to a document.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user who is authoring the change. If the user has no permission, no changes are saved.
	 */
	public function save( User $user )
	{
		if ( isset( $this->id ) )
		{
			if ( $this->is_editable( $user ) )
			{
				$this->database->update_document( $this->id, $this->data );
				$this->type->update( $this );
			}
		}
		else
		{
			if ( $this->is_creatable( $user ) )
			{
				$this->id = $this->database->insert_document( $this->origin->id, $this->owner->id, $this->type->slug, $this->data );
				$this->type->insert( $this );
			}
		}
	}

	/**
	 * Delete this document from the database.
	 * After deletion, the document instance reverts to an unsaved draft. If saved again, it will be saved as a new document with a new ID.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user who is authoring the deletion. If the user has no permission, the document is not deleted.
	 */
	public function delete( User $user )
	{
		if ( isset( $this->id ) && $this->is_deletable( $user ) )
		{
			$this->database->delete_document( $this->id );
			$this->id = null;
		}
	}

	/**
	 * Determines whether a given user has permission to write to the document's origin.
	 * Users have permission to write to a domain if they have edit permission for that domain.
	 * Additionally, users have permission to write to the origin of their origin, if it exists.
	 * The genesis user has permission for any domain.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	private function origin_is_writable( User $user ): bool
	{
		return $user->id === 0 ||
		$user->origin->id !== 0 && (
			$this->origin->is( $user->origin->origin ) ||
			$this->origin->is_editable( $user )
		);
	}

	/**
	 * Determines whether a given user has permission to save this document to the database for the first time.
	 * This requires the document type to grant permission.
	 * If the document is not editable by the user, permission is denied.
	 *
	 * Note: the method still may return `true` if the document has already been created.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_creatable( User $user ): bool
	{
		return $this->is_editable( $user ) &&
		$this->type->is_creatable( $user, $this->owner, $this->origin, $this->data );
	}

	/**
	 * Determines whether unauthenticated access to a given domain has permission to read this document.
	 * Domains only give unauthenticated read permission to documents originating in their direct ancestry, or documents originating from domains originating from their direct ancestry.
	 * It also requires the document type to grant permission.
	 *
	 * @since 1.0.0
	 *
	 * @param Domain $domain The domain being accessed.
	 *
	 * @return bool True if document is readable, false if not.
	 */
	public function is_readable_by_public( Domain $domain ): bool
	{
		return $this->origin->origin->in( $domain->ancestry ) &&
		$this->type->is_readable_by_public( $domain, $this->owner, $this->origin, $this->data );
	}

	/**
	 * Determines whether a given user has permission to read this document.
	 * If the user has no permission to read the document's origin, the user is denied permission to read this document.
	 * If the document is readable by the public, the user automatically gets permission.
	 * If not, the document type must explicitly grant permission.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_readable( User $user ): bool
	{
		return $this->origin->is_readable( $user ) && (
			$this->type->is_readable( $user, $this->owner, $this->origin, $this->data ) ||
			$this->is_readable_by_public( $user->origin )
		);
	}

	/**
	 * Determines whether a given user has permission to edit this document.
	 * This requires the document type to grant permission.
	 * If the document's origin is not writable by the user, permission is denied.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_editable( User $user ): bool
	{
		return $this->origin_is_writable( $user ) &&
		$this->type->is_editable( $user, $this->owner, $this->origin, $this->data );
	}

	/**
	 * Determines whether a given user has permission to delete this document.
	 * This requires the document type to grant permission.
	 * If the document's origin is not writable by the user, permission is denied.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_deletable( User $user ): bool
	{
		return $this->origin_is_writable( $user ) &&
		$this->type->is_deletable( $user, $this->owner, $this->origin, $this->data );
	}

	/**
	 * Determines whether two documents are the same.
	 * If both have an ID, the documents are the same if their ID's are the same.
	 * If one or both is without an ID, the documents are the same if they are the same instance.
	 *
	 * @since 1.0.0
	 *
	 * @param Document $other The document being compared with this one.
	 *
	 * @return bool True if documents are the same, false if not.
	 */
	public function is( Document $other ): bool
	{
		return $this === $other || isset( $this->id ) && isset( $other->id ) && $this->id === $other->id;
	}

	/**
	 * Determines whether this document is the same as any of the documents in an array.
	 * The `is` method is called on each document in the array until a match is found.
	 *
	 * @since 1.0.0
	 *
	 * @param Document[] $array An array of documents to compare against.
	 *
	 * @return bool True if a match was found, false if not.
	 */
	public function in( array $array ): bool
	{
		foreach ( $array as $other )
		{
			if ( $this->is( $other ) )
			{
				return true;
			}
		}

		return false;
	}
}
