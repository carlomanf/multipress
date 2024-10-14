<?php

namespace Multipress;

/**
 * Core class for representing domains.
 *
 * @since 1.0.0
 */
final class Domain
{
	/**
	 * The database for saving this domain.
	 * Null for the genesis domain.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Database|null
	 */
	private $database = null;

	/**
	 * The numeric ID of this domain.
	 * 0 for the genesis domain.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $id = 0;

	/**
	 * The hostname of this domain, e.g. `example.org`.
	 * This variable is set through by the `genesis` and `new` static methods.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $name;

	/**
	 * The pre-existing domain on which this domain originates.
	 * Null for the genesis domain.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Domain
	 */
	private $origin = null;

	/**
	 * The user with ownership over this domain.
	 * The genesis domain is owned by the genesis user.
	 * This variable is set through by the `genesis` and `new` static methods.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var User
	 */
	private $owner;

	/**
	 * Array of string keys and string values for this domain.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private $data = array();

	/**
	 * Internal utility to prevent cycles in the origin relationship.
	 * The value is not accessible via any public method.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $constructing = array();

	/**
	 * Default constructor, not available for public use.
	 * Use the `genesis` or `new` static methods instead.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 */
	private function __construct()
	{
		// Empty method.
	}

	/**
	 * Constructs the genesis domain.
	 * The genesis domain has an ID of 0.
	 * The genesis domain has no database, but stores its data in the environment instance.
	 * The genesis domain is the only domain allowed to have an origin of `null`.
	 * The genesis domain is always owned by the genesis user and can't be deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param Environment $environment The environment instance.
	 *
	 * @return Domain Genesis domain.
	 */
	public static function genesis( Environment $environment ): Domain
	{
		$genesis = new Domain();
		$genesis->name = $environment->genesis_domain;
		$genesis->owner = User::genesis( $environment );
		$genesis->data = $environment->genesis_domain_data;
		return $genesis;
	}

	/**
	 * Constructs a new, unsaved domain.
	 *
	 * @since 1.0.0
	 *
	 * @param Database $database The database to which this domain will be saved.
	 * @param string $name The hostname of this domain, e.g. `example.org`.
	 * @param User $owner The user with ownership over this domain.
	 * @param Domain $origin The pre-existing domain on which this domain originates.
	 *
	 * @return Domain New domain.
	 */
	public static function new( Database $database, string $name, User $owner, Domain $origin ): Domain
	{
		$new = new Domain();
		$new->database = $database;
		$new->id = null;
		$new->name = $name;
		$new->origin = $origin;
		$new->owner = $owner;
		return $new;
	}

	/**
	 * Getter.
	 *
	 * Values of the $data array can be accessed by passing the key.
	 * The `users_can_register` key is special, returning a bool value with a default value of `true`.
	 * The `depth_allowed` key is special, returning an int value with a default value of -1.
	 * 
	 * It should be noted that although the genesis domain has an origin of `null`, passing `origin` as the key does not return `null` but the genesis domain itself.
	 *
	 * The `ancestry` key can be used to access the chain of origins, up to and including the genesis domain.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Key.
	 *
	 * @return mixed Value.
	 */
	public function __get( $key )
	{
		if ( 'id' === $key )
		{
			return $this->id;
		}

		if ( 'name' === $key )
		{
			return $this->name;
		}

		if ( 'owner' === $key )
		{
			return $this->owner;
		}

		if ( 'origin' === $key )
		{
			return isset( $this->origin ) ? $this->origin : $this;
		}

		if ( 'ancestry' === $key )
		{
			$ancestry = array();
			$next = $this->__get( 'origin' );

			while ( isset( $next ) && !$next->in( $ancestry ) )
			{
				$ancestry[] = $next;
				$next = $next->origin;
			}

			return $ancestry;
		}

		if ( 'data' === $key )
		{
			return $this->data;
		}

		if ( 'users_can_register' === $key )
		{
			return isset( $this->data[ 'users_can_register' ] ) ? (bool) $this->data[ 'users_can_register' ] : true;
		}

		if ( 'depth_allowed' === $key )
		{
			return isset( $this->data[ 'depth_allowed' ] ) ? (int) $this->data[ 'depth_allowed' ] : -1;
		}

		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
	}

	/**
	 * Private method that calls the `new` method on rows from the database.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 *
	 * @param array[] $rows {
	 *     Database rows, indexed by domain ID.
	 *
	 *     @type string $name Domain hostname, e.g. `example.org`.
	 *     @type string $owner Numeric string representing the domain owner ID.
	 *     @type string $origin Numeric string representing the domain origin ID.
	 *     @type string[] $data Additional data for this domain.
	 * }
	 * @param Environment $environment The environment instance, for validating data.
	 * @param bool $single Whether to construct a domain for only the first row or all rows.
	 *
	 * @return Domain[]|Domain|null An array of valid domains if `$single` is false. If `$single` is true, the method returns either a single domain or null if there were no valid domains.
	 */
	private static function construct( array $rows, Environment $environment, bool $single = false )
	{
		$domains = array();

		foreach ( $rows as $id => $row )
		{
			if ( isset( self::$constructing[ (int) $id ] ) )
			{
				continue;
			}

			self::$constructing[ (int) $id ] = true;
			$origin = Domain::get_by_id( $environment, (int) $row['origin'] );
			$owner = User::get_by_id( $environment, (int) $row['owner'] );
			unset( self::$constructing[ (int) $id ] );

			if ( !isset( $origin ) || !isset( $owner ) )
			{
				continue;
			}

			$domain = Domain::new( $environment->database, (string) $row['name'], $owner, $origin );
			$domain->data = (array) $row['data'];
			$domain->id = (int) $id;

			if ( $single )
			{
				return $domain;
			}

			$domains[ (int) $id ] = $domain;
		}

		return $single ? null : $domains;
	}

	/**
	 * Returns a domain with a particular ID, if it exists.
	 *
	 * @since 1.0.0
	 *
	 * @param Environment $environment The environment instance, for validating data.
	 * @param int $id The requested ID.
	 *
	 * @return Domain|null The domain with the requested ID, or `null` if it could not be found.
	 */
	public static function get_by_id( Environment $environment, int $id )
	{
		if ( $id === 0 )
		{
			return Domain::genesis( $environment );
		}
		else
		{
			if ( empty( $cache = $environment->get_from_domain_cache( 'id', (string) $id ) ) )
			{
				if ( !is_null( $domain = self::construct( $environment->database->get_domain_by_id( $id ), $environment, true ) ) )
				{
					$environment->save_to_domain_cache( 'id', (string) $domain->id, $domain );
					$environment->save_to_domain_cache( 'name', $domain->name, $domain );
				}

				return $domain;
			}
			else
			{
				return isset( $cache[ $id ] ) ? $cache[ $id ] : null;
			}
		}
	}

	/**
	 * Returns a domain with a particular name, if it exists.
	 *
	 * @since 1.0.0
	 *
	 * @param Environment $environment The environment instance, for validating data.
	 * @param string $name The requested hostname, e.g. `example.org`.
	 *
	 * @return Domain|null The domain with the requested name, or `null` if it could not be found.
	 */
	public static function get_by_name( Environment $environment, string $name )
	{
		if ( $name === $environment->genesis_domain )
		{
			return Domain::genesis( $environment );
		}
		else
		{
			if ( empty( $cache = $environment->get_from_domain_cache( 'name', $name ) ) )
			{
				if ( !is_null( $domain = self::construct( $environment->database->get_domain_by_name( $name ), $environment, true ) ) )
				{
					$environment->save_to_domain_cache( 'id', (string) $domain->id, $domain );
					$environment->save_to_domain_cache( 'name', $domain->name, $domain );
				}

				return $domain;
			}
			else
			{
				return $cache[ min( array_keys( $cache ) ) ];
			}
		}
	}

	/**
	 * The callback method for when this domain needs to return a 404 error.
	 *
	 * @since 1.0.0
	 */
	public function error_404()
	{
		echo 'Not found';
	}

	/**
	 * Query a user's permission to create domains originating from this one.
	 *
	 * A positive integer N indicates they have the permission to create a chain of N successive domains, each originating from the last.
	 * Any negative integer indicates there is no restriction for this user.
	 *
	 * Users only have the permission to create domains originating from the origin of their origin, or from domains they can edit.
	 * The `depth_allowed` key in the `$data` array can be used to tighten these restrictions.
	 * The genesis user is not bound by the restrictions and can create domains originating from any domain.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being queried.
	 *
	 * @return Domain|null The domain with the requested name, or `null` if it could not be found.
	 */
	public function depth_allowed( User $user ): int
	{
		if ( $user->id === 0 )
		{
			return -1;
		}

		if ( !isset( $user->origin->origin ) || !$this->is( $user->origin->origin ) && !$this->is_editable( $user ) )
		{
			return 0;
		}

		$depth_allowed = $this->depth_allowed;

		if ( !isset( $this->origin ) )
		{
			return $depth_allowed;
		}

		$max_depth_allowed = $this->origin->depth_allowed( $this->owner ) - 1;

		if ( $max_depth_allowed < 0 )
		{
			$max_depth_allowed++;
		}

		if ( $depth_allowed < 0 )
		{
			return $max_depth_allowed;
		}
		else
		{
			if ( $max_depth_allowed < 0 )
			{
				return $depth_allowed;
			}
			else
			{
				return min( $depth_allowed, $max_depth_allowed );
			}
		}
	}

	/**
	 * Save to the database any changes made to a domain, unless it is the genesis domain.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user who is authoring the change. If the user has no permission, no changes are saved.
	 */
	public function save( User $user )
	{
		if ( isset( $this->id ) )
		{
			if ( $this->id !== 0 && $this->is_editable( $user ) )
			{
				$this->database->update_domain( $this->id, array() );
			}
		}
		else
		{
			if ( $this->is_creatable( $user ) )
			{
				$this->id = $this->database->insert_domain( array() );
			}
		}
	}

	/**
	 * Delete this domain from the database, unless it is the genesis domain.
	 * After deletion, the domain instance reverts to an unsaved draft. If saved again, it will be saved as a new domain with a new ID.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user who is authoring the deletion. If the user has no permission, the domain is not deleted.
	 */
	public function delete( User $user )
	{
		if ( !empty( $this->id ) && $this->is_deletable( $user ) )
		{
			$this->database->delete_domain( $this->id );
			$this->id = null;
		}
	}

	/**
	 * Determines whether a given user has permission to save this domain to the database for the first time.
	 * A user can only create a domain if its origin permits them to.
	 * In order for a user to create a domain under someone else's authorship, they must have edit access to the origin.
	 *
	 * Note: the method still may return `true` if the domain has already been created. However, it always returns `false` for the genesis domain.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_creatable( User $user ): bool
	{
		return isset( $this->origin ) &&
		$this->origin->depth_allowed( $user ) !== 0 && (
			$this->owner->is( $user ) ||
			$this->origin->is_editable( $user )
		);
	}

	/**
	 * Determines whether a given user has permission to read this domain.
	 * The direct ancestry, plus domains originating from the direct ancestry, of the user's origin are readable.
	 * Domains editable by the user are also readable.
	 * The genesis domain is always readable.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_readable( User $user ): bool
	{
		return !isset( $this->origin ) || $this->is_editable( $user ) || $this->origin->in( $user->origin->ancestry );
	}

	/**
	 * Determines whether a given user has permission to edit this domain.
	 * The behaviour of this method is nearly identical to `is_creatable`, except the genesis user has permission to edit the genesis domain.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_editable( User $user ): bool
	{
		return $user->id === 0 || $this->is_creatable( $user );
	}

	/**
	 * Determines whether a given user has permission to delete this domain.
	 * The behaviour of this method is currently identical to `is_editable`.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_deletable( User $user ): bool
	{
		return $this->is_editable( $user );
	}

	/**
	 * Determines whether two domains are the same.
	 * If both have an ID, the domains are the same if their ID's are the same.
	 * If one or both is without an ID, the domains are the same if they are the same instance.
	 *
	 * @since 1.0.0
	 *
	 * @param Domain $other The user being compared with this one.
	 *
	 * @return bool True if domains are the same, false if not.
	 */
	public function is( Domain $other ): bool
	{
		return $this === $other || isset( $this->id ) && isset( $other->id ) && $this->id === $other->id;
	}

	/**
	 * Determines whether this domain is the same as any of the domains in an array.
	 * The `is` method is called on each domain in the array until a match is found.
	 *
	 * @since 1.0.0
	 *
	 * @param Domain[] $array An array of domains to compare against.
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
