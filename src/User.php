<?php

namespace Multipress;

/**
 * Core class for representing users.
 *
 * @since 1.0.0
 */
final class User
{
	/**
	 * The database for saving this user.
	 * Null for the genesis user.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Database|null
	 */
	private $database = null;

	/**
	 * The numeric ID of this domain.
	 * 0 for the genesis user.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $id = 0;

	/**
	 * The domain on which this user originates.
	 * Null for the genesis user.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Domain
	 */
	private $origin = null;

	/**
	 * Array of string keys and string values for this user.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private $data = array();

	/**
	 * Internal utility to prevent cycles in the user/domain relationships.
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
	 * Constructs the genesis user.
	 * The genesis user has an ID of 0.
	 * The genesis user has no database, but stores their data in the environment instance.
	 * The genesis user is the only user allowed to have an origin of `null`.
	 * The genesis user can't be deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param Environment $environment The environment instance.
	 *
	 * @return User Genesis user.
	 */
	public static function genesis( Environment $environment ): User
	{
		$genesis = new User();
		$genesis->data = $environment->genesis_user_data;
		return $genesis;
	}

	/**
	 * Constructs a new, unsaved user.
	 *
	 * @since 1.0.0
	 *
	 * @param Database $database The database to which this user will be saved.
	 * @param Domain $origin The domain on which this user originates.
	 *
	 * @return User New user.
	 */
	public static function new( Database $database, Domain $origin ): User
	{
		$new = new User();
		$new->database = $database;
		$new->id = null;
		$new->origin = $origin;
		return $new;
	}

	/**
	 * Getter.
	 * Values of the `$data` array can be accessed by passing the key.
	 *
	 * `id`, `origin` and `data` are reserved words and access their respective fields rather than the `$data` array.
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

		if ( 'origin' === $key )
		{
			return $this->origin;
		}

		if ( 'data' === $key )
		{
			return $this->data;
		}

		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
	}

	/**
	 * Setter.
	 * Values of the `$data` array can be accessed by passing the key.
	 *
	 * `id`, `origin` and `data` are reserved words and do not save to the `$data` array.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Key.
	 * @param string $value Value.
	 */
	public function __set( string $key, string $value )
	{
		if ( !in_array( $key, array( 'id', 'origin', 'data' ), true ) )
		{
			$this->data[ $key ] = $value;
		}
	}

	/**
	 * Private method that calls the `new` method on rows from the database.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 *
	 * @param array[] $rows {
	 *     Database rows, indexed by user ID.
	 *
	 *     @type string $origin Numeric string representing the user origin ID.
	 *     @type string[] $data Additional data for this user.
	 * }
	 * @param Environment $environment The environment instance, for validating data.
	 * @param bool $single Whether to construct a user for only the first row or all rows.
	 *
	 * @return User[]|User|null An array of valid users if `$single` is false. If `$single` is true, the method returns either a single user or null if there were no valid users.
	 */
	private static function construct( array $rows, Environment $environment, bool $single = false )
	{
		$users = array();

		foreach ( $rows as $id => $row )
		{
			if ( isset( self::$constructing[ (int) $id ] ) )
			{
				continue;
			}

			self::$constructing[ (int) $id ] = true;
			$origin = Domain::get_by_id( $environment, (int) $row['origin'] );
			unset( self::$constructing[ (int) $id ] );

			if ( !isset( $origin ) )
			{
				continue;
			}

			$user = User::new( $environment->database, $origin );
			$user->data = (array) $row['data'];
			$user->id = (int) $id;

			if ( $single )
			{
				return $user;
			}

			$users[ (int) $id ] = $user;
		}

		return $single ? null : $users;
	}

	/**
	 * Returns a user with a particular ID, if they exist.
	 *
	 * @since 1.0.0
	 *
	 * @param Environment $environment The environment instance, for validating data.
	 * @param int $id The requested ID.
	 *
	 * @return User|null The user with the requested ID, or `null` if it could not be found.
	 */
	public static function get_by_id( Environment $environment, int $id )
	{
		if ( $id === 0 )
		{
			return User::genesis( $environment );
		}
		else
		{
			if ( empty( $cache = $environment->get_from_user_cache( 'id', (string) $id ) ) )
			{
				if ( !is_null( $user = self::construct( $environment->database->get_user_by_id( $id ), $environment, true ) ) )
				{
					$environment->save_to_user_cache( 'id', (string) $user->id, $user );
				}

				return $user;
			}
			else
			{
				return isset( $cache[ $id ] ) ? $cache[ $id ] : null;
			}
		}
	}

	/**
	 * Save to the database any changes made to a user, unless they are the genesis user.
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
				$this->database->update_user( $this->id, $this->data );
			}
		}
		else
		{
			if ( $this->is_creatable( $user ) )
			{
				$this->id = $this->database->insert_user( $this->origin->id, $this->data );
			}
		}
	}

	/**
	 * Delete this user from the database, unless they are the genesis user.
	 * After deletion, the user instance reverts to an unsaved draft. If saved again, it will be saved as a new user with a new ID.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user who is authoring the deletion. If the authoring user has no permission, the target user is not deleted.
	 */
	public function delete( User $user )
	{
		if ( !empty( $this->id ) && $this->is_deletable( $user ) )
		{
			$this->database->delete_user( $this->id );
			$this->id = null;
		}
	}

	/**
	 * Determines whether a given user has permission to save this user to the database for the first time.
	 * A user can create themselves if their origin permits them to.
	 * In order for a user to create a user who is not themselves, they must have edit access to the origin.
	 *
	 * Note: the method still may return `true` if the user has already been created. However, it always returns `false` for the genesis user.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_creatable( User $user ): bool
	{
		return isset( $this->origin ) && (
			$this->origin->is_editable( $user ) ||
			$this->is( $user ) && $this->origin->users_can_register
		);
	}

	/**
	 * Determines whether a given user has permission to read this user.
	 * The behaviour of this method is currently identical to `is_editable`.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_readable( User $user ): bool
	{
		return $this->is_editable( $user );
	}

	/**
	 * Determines whether a given user has permission to edit this user.
	 * The behaviour of this method is nearly identical to `is_creatable`, except all users can always edit themselves.
	 *
	 * @since 1.0.0
	 *
	 * @param User $user The user whose permissions are being checked.
	 *
	 * @return bool True if user has permission, false if not.
	 */
	public function is_editable( User $user ): bool
	{
		return $this->is_creatable( $user ) || $this->is( $user );
	}

	/**
	 * Determines whether a given user has permission to delete this user.
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
	 * Determines whether two users are the same.
	 * If both have an ID, the users are the same if their ID's are the same.
	 * If one or both is without an ID, the users are the same if they are the same instance.
	 *
	 * @since 1.0.0
	 *
	 * @param User $other The user being compared with this one.
	 *
	 * @return bool True if users are the same, false if not.
	 */
	public function is( User $other ): bool
	{
		return $this === $other || isset( $this->id ) && isset( $other->id ) && $this->id === $other->id;
	}

	/**
	 * Determines whether this user is the same as any of the users in an array.
	 * The `is` method is called on each user in the array until a match is found.
	 *
	 * @since 1.0.0
	 *
	 * @param User[] $array An array of users to compare against.
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
