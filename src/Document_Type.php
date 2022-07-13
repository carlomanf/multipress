<?php

namespace Multipress;

/**
 * Core abstract class to be extended by document types.
 *
 * @since 1.0.0
 */
abstract class Document_Type
{
	/**
	 * The slug of this document type.
	 * This will be visible in all document permalinks.
	 * Set through the `__set` method and can not be changed once set.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $slug = '';

	/**
	 * Getter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Key.
	 *
	 * @return mixed Value.
	 */
	final public function __get( $key )
	{
		if ( 'slug' === $key )
		{
			return $this->slug;
		}
	}

	/**
	 * Setter.
	 * Only used to set the slug, which can only be set once.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key Key.
	 * @param mixed $value Value.
	 */
	final public function __set( $key, $value )
	{
		if ( 'slug' === $key && empty( $this->slug ) )
		{
			$this->slug = $value;
		}
	}

	/**
	 * Determines whether two document types are the same.
	 * They are the same if their slugs are the same.
	 *
	 * @since 1.0.0
	 *
	 * @param Document_Type $other The document type being compared with this one.
	 *
	 * @return bool True if document types are the same, false if not.
	 */
	final public function is( Document_Type $other ): bool
	{
		return $other->slug === $this->slug;
	}

	/**
	 * Determines whether this document type is the same as any of the documents in an array.
	 * The `is` method is called on each document type in the array until a match is found.
	 *
	 * @since 1.0.0
	 *
	 * @param Document_Type[] $array An array of document types to compare against.
	 *
	 * @return bool True if a match was found, false if not.
	 */
	final public function in( array $array ): bool
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

	/**
	 * Returns documents of this type with a specific data key and value.
	 * Can be over-ridden by subclasses.
	 *
	 * @since 1.0.0
	 *
	 * @param Environment $environment The environment instance, for validating the type.
	 * @param string $key The data key.
	 * @param mixed $value The data value. Will be converted to string.
	 *
	 * @return Document[] Array of documents.
	 */
	public function get_by_data( Environment $environment, string $key, $value ): array
	{
		if ( empty( $cache = $environment->get_from_document_cache( $key, (string) $value ) ) )
		{
			$documents = array();

			foreach ( Document::get_by_type( $environment, $this->slug ) as $document )
			{
				if ( $document->data[ $key ] === $value )
				{
					$documents[] = $document;
					$environment->save_to_document_cache( $key, (string) $value, $document );
				}
			}

			return $documents;
		}
		else
		{
			return $cache;
		}
	}

	/**
	 * Registers this document type with an environment.
	 *
	 * @since 1.0.0
	 *
	 * @param Environment $environment The environment instance.
	 */
	public abstract static function register( Environment $environment );

	/**
	 * Callback method for rendering a document of this type.
	 * This method is called when a request string is sent starting with this document type slug.
	 *
	 * @since 1.0.0
	 *
	 * @param Environment $environment The current environment.
	 * @param string $request The request string with the document type slug removed.
	 */
	public abstract function render( Environment $environment, string $request );

	/**
	 * Determines whether a given user has permission to save a document of this type to the database for the first time.
	 *
	 * Note: calling `is_creatable` on a document of this type still may return `false` if this method returns true.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 * @param User $owner The user with ownership of this document.
	 * @param Domain $origin The domain on which this document originates.
	 * @param array $data The data of this document.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public abstract function is_creatable( User $user, User $owner, Domain $origin, array $data ): bool;

	/**
	 * Determines whether unauthenticated access to a given domain has permission to read this document.
	 *
	 * Note: calling `is_readable_by_public` on a document of this type still may return `false` if this method returns true.
	 *
	 * @since 1.0.0
	 *
	 * @param Domain $domain The domain being accessed.
	 * @param User $owner The user with ownership of this document.
	 * @param Domain $origin The domain on which this document originates.
	 * @param array $data The data of this document.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public abstract function is_readable_by_public( Domain $domain, User $owner, Domain $origin, array $data ): bool;

	/**
	 * Determines whether a given user has permission to read this document.
	 *
	 * Note: calling `is_readable` on a document of this type still may return `false` if this method returns true.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 * @param User $owner The user with ownership of this document.
	 * @param Domain $origin The domain on which this document originates.
	 * @param array $data The data of this document.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public abstract function is_readable( User $user, User $owner, Domain $origin, array $data ): bool;

	/**
	 * Determines whether a given user has permission to edit this document.
	 *
	 * Note: calling `is_editable` on a document of this type still may return `false` if this method returns true.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 * @param User $owner The user with ownership of this document.
	 * @param Domain $origin The domain on which this document originates.
	 * @param array $data The data of this document.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public abstract function is_editable( User $user, User $owner, Domain $origin, array $data ): bool;

	/**
	 * Determines whether a given user has permission to delete this document.
	 *
	 * Note: calling `is_deletable` on a document of this type still may return `false` if this method returns true.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 * @param User $owner The user with ownership of this document.
	 * @param Domain $origin The domain on which this document originates.
	 * @param array $data The data of this document.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public abstract function is_deletable( User $user, User $owner, Domain $origin, array $data ): bool;

	/**
	 * Callback method for when a document of this type is updated.
	 * Can be over-ridden by subclasses.
	 *
	 * @since 1.0.0
	 */
	public function insert( Document $document )
	{
		// Empty method.
	}

	/**
	 * Callback method for when a document of this type is updated.
	 * Can be over-ridden by subclasses.
	 *
	 * @since 1.0.0
	 */
	public function update( Document $document )
	{
		// Empty method.
	}
}
