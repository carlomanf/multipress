<?php

namespace Multipress;

/**
 * Database implementation to handle cookies.
 *
 * @since 1.2.0
 */
class Cookie_Database implements Database
{
	/**
	 * Environment instance.
	 * Set through the constructor.
	 *
	 * @access private
	 *
	 * @since 1.2.0
	 * @var Environment
	 */
	private $environment;

	/**
	 * Data to save to cookies when user is instantiated.
	 * Set through the constructor.
	 *
	 * @access private
	 *
	 * @since 1.2.0
	 * @var array
	 */
	private $starter_data;

	/**
	 * Expiry time for cookies.
	 * Set through the constructor.
	 *
	 * @access private
	 *
	 * @since 1.2.0
	 * @var int
	 */
	private $expiry;

	/**
	 * User instance using this database.
	 * Set through the `get_user` method.
	 *
	 * @access private
	 *
	 * @since 1.2.0
	 * @var User
	 */
	private $user;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param Environment $environment The environment in which these cookies are being used.
	 * @param array $starter_data Data to save to cookies when user is instantiated. Optional with an empty array as default.
	 * @param int $expiry Expiry time. Optional and defaulting to 30 days from the current time if 0 is provided.
	 */
	public function __construct( Environment $environment, array $starter_data = array(), int $expiry = 0 )
	{
		$this->environment = $environment;
		$this->starter_data = $starter_data;
		$this->expiry = empty( $expiry ) ? time() + 60 * 60 * 24 * 30 : $expiry;
	}

	/**
	 * Return the user instance using this database.
	 *
	 * @since 1.2.0
	 *
	 * @return User User object using this database.
	 */
	public function get_user(): User
	{
		if ( isset( $this->user ) )
		{
			return $this->user;
		}
		else
		{
			$this->user = User::new( $this, $this->environment->current_domain );

			foreach ( $this->starter_data as $key => $value )
			{
				$this->user->__set( (string) $key, (string) $value );
			}

			$this->user->save( $this->user );
			return $this->user;
		}
	}

	/**
	 * Query the database for documents of a particular type.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type Document type slug.
	 *
	 * @return array[] {
	 *     Database rows, indexed by document ID.
	 *
	 *     @type string $type Document type slug.
	 *     @type string $owner Numeric string representing the document owner ID.
	 *     @type string $origin Numeric string representing the document origin ID.
	 *     @type string[] $data Additional data for this document.
	 * }
	 */
	public function get_documents_by_type( string $type ): array
	{
		return $this->environment->database->get_documents_by_type( $type );
	}

	/**
	 * Query the database for domain(s) of a particular ID.
	 *
	 * @since 1.2.0
	 *
	 * @param int $id Domain ID.
	 *
	 * @return array[] {
	 *     Database rows, indexed by domain ID.
	 *
	 *     @type string $name Domain hostname, e.g. `example.org`.
	 *     @type string $owner Numeric string representing the domain owner ID.
	 *     @type string $origin Numeric string representing the domain origin ID.
	 *     @type string[] $data Additional data for this domain.
	 * }
	 */
	public function get_domain_by_id( int $id ): array
	{
		return $this->environment->database->get_domain_by_id( $id );
	}

	/**
	 * Query the database for domain(s) of a particular hostname.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name Domain hostname, e.g. `example.org`.
	 *
	 * @return array[] {
	 *     Database rows, indexed by domain ID.
	 *
	 *     @type string $name Domain hostname, e.g. `example.org`.
	 *     @type string $owner Numeric string representing the domain owner ID.
	 *     @type string $origin Numeric string representing the domain origin ID.
	 *     @type string[] $data Additional data for this domain.
	 * }
	 */
	public function get_domain_by_name( string $name ): array
	{
		return $this->environment->database->get_domain_by_name( $name );
	}

	/**
	 * Query the database for user(s) of a particular ID.
	 *
	 * @since 1.2.0
	 *
	 * @param int $id User ID.
	 *
	 * @return array[] {
	 *     Database rows, indexed by user ID.
	 *
	 *     @type string $origin Numeric string representing the user origin ID.
	 *     @type string[] $data Additional data for this user.
	 * }
	 */
	public function get_user_by_id( int $id ): array
	{
		return $this->environment->database->get_user_by_id( $id );
	}

	/**
	 * Insert a new document into the database.
	 *
	 * @since 1.2.0
	 *
	 * @param int $origin Document origin ID.
	 * @param int $owner Document owner ID.
	 * @param string $type Document type.
	 * @param array $changes Initial data to apply to the document.
	 *
	 * @return int The ID of the document.
	 */
	public function insert_document( int $origin, int $owner, string $type, array $changes ): int
	{
		return $this->environment->database->insert_document( $origin, $owner, $type, $changes );
	}

	/**
	 * Insert a new domain into the database.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name Domain name.
	 * @param int $origin Domain origin ID.
	 * @param int $owner Domain owner ID.
	 * @param array $changes Initial data to apply to the domain.
	 *
	 * @return int The ID of the domain.
	 */
	public function insert_domain( string $name, int $origin, int $owner, array $changes ): int
	{
		return $this->environment->database->insert_domain( $name, $origin, $owner, $changes );
	}

	/**
	 * Set the user's cookies.
	 *
	 * @since 1.2.0
	 *
	 * @param int $origin User origin ID.
	 * @param array $changes Initial data to apply to the user.
	 *
	 * @return int Always returns -1.
	 */
	public function insert_user( int $origin, array $changes ): int
	{
		foreach ( $changes as $key => $value )
		{
			$_COOKIE[ (string) $key ] = (string) $value;
			setcookie( (string) $key, (string) $value, $this->expiry, '/' );
		}

		return -1;
	}

	/**
	 * Update a document in the database.
	 *
	 * @since 1.2.0
	 *
	 * @param int $id Document ID.
	 * @param array $changes Changes to apply to the document.
	 */
	public function update_document( int $id, array $changes )
	{
		$this->environment->database->update_document( $id, $changes );
	}

	/**
	 * Update a domain in the database.
	 *
	 * @since 1.2.0
	 *
	 * @param int $id Domain ID.
	 * @param array $changes Changes to apply to the domain.
	 */
	public function update_domain( int $id, array $changes )
	{
		$this->environment->database->update_domain( $id, $changes );
	}

	/**
	 * Update the user's cookies.
	 *
	 * @since 1.2.0
	 *
	 * @param int $id User ID.
	 * @param array $changes Changes to apply to the user.
	 */
	public function update_user( int $id, array $changes )
	{
		$this->insert_user( $this->environment->current_domain->id, $changes );
	}

	/**
	 * Delete the document with a particular ID from the database.
	 *
	 * @since 1.2.0
	 *
	 * @param int $id Document ID.
	 */
	public function delete_document( int $id )
	{
		$this->environment->database->delete_document( $id );
	}

	/**
	 * Delete the domain with a particular ID from the database.
	 *
	 * @since 1.2.0
	 *
	 * @param int $id Domain ID.
	 */
	public function delete_domain( int $id )
	{
		$this->environment->database->delete_domain( $id );
	}

	/**
	 * Delete the user with a particular ID from the database.
	 *
	 * @since 1.2.0
	 *
	 * @param int $id User ID.
	 */
	public function delete_user( int $id )
	{
		$this->environment->database->delete_user( $id );
	}
}
