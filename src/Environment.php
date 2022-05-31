<?php

namespace Multipress;

/**
 * Environment class.
 *
 * @since 1.0.0
 */
class Environment
{
	/**
	 * The database used by this environment.
	 * Set through the constructor.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Database
	 */
	private $database = null;

	/**
	 * The hostname of the genesis domain, e.g. `example.org`.
	 * Set through the constructor.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $genesis_domain;

	/**
	 * Array of string keys and string values for the genesis domain.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private $genesis_domain_data = array();

	/**
	 * Array of string keys and string values for the genesis user.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private $genesis_user_data = array();

	/**
	 * The current domain being accessed.
	 * This is allowed to be `null` if no domain is being accessed.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Domain|null
	 */
	private $current_domain = null;

	/**
	 * The currently authenticated user, or `null` if no user is authenticated.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var User|null
	 */
	private $current_user = null;

	/**
	 * The document types registered for this environment.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Document_Type[]
	 */
	private $types = array();

	/**
	 * The cache of known documents.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Document[][][]
	 */
	private $document_cache = array();

	/**
	 * The cache of known domains.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var Domain[][][]
	 */
	private $domain_cache = array();

	/**
	 * The cache of known users.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 * @var User[][][]
	 */
	private $user_cache = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Database $database The database for this environment.
	 * @param string $genesis_domain The hostname of the genesis domain, e.g. `example.org`.
	 * @param array $genesis_domain_data Data for the genesis domain. Optional with an empty array as default.
	 * @param array $genesis_user_data Data for the genesis user. Optional with an empty array as default.
	 */
	public function __construct( Database $database, string $genesis_domain, array $genesis_domain_data = array(), array $genesis_user_data = array() )
	{
		$this->database = $database;
		$this->genesis_domain = $genesis_domain;
		$this->genesis_domain_data = $genesis_domain_data;
		$this->genesis_user_data = $genesis_user_data;
		$this->current_domain = Domain::get_by_name( $this, $_SERVER['HTTP_HOST'] );
	}

	/**
	 * Register a document type with this environment.
	 * It must have a valid slug that does not match an already registered document type.
	 *
	 * @since 1.0.0
	 *
	 * @param Document_Type $type The type to be added.
	 *
	 * @return bool Whether the type was successfully added.
	 */
	public function add_type( Document_Type $type ): bool
	{
		$slug = $type->slug;

		if ( empty( $slug ) || isset( $this->types[ $slug ] ) )
		{
			return false;
		}
		else
		{
			$this->types[ $type->slug ] = $type;
			return true;
		}
	}

	/**
	 * Retrieve a document type registered with this environment having a given slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The requested slug.
	 *
	 * @return Document_Type|null The type with the requested slug, or `null` if none was found.
	 */
	public function get_type( string $slug )
	{
		return isset( $this->types[ $slug ] ) ? $this->types[ $slug ] : null;
	}

	/**
	 * Private method that saves objects to the caches.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 *
	 * @param array $cache The cache to save to, passed by reference.
	 * @param string $section The section of the cache to add the object to.
	 * @param string $key Within the section, the key to store the object under.
	 * @param mixed $data The object to be stored.
	 */
	private function save_to_cache( array &$cache, string $section, string $key, $data )
	{
		if ( isset( $cache[ $section ][ $key ] ) )
		{
			$cache[ $section ][ $key ][] = $data;
		}
		else
		{
			$cache[ $section ][ $key ] = array( $data );
		}
	}

	/**
	 * Private method that retrieves objects from the caches.
	 *
	 * @access private
	 *
	 * @since 1.0.0
	 *
	 * @param array $cache The cache to save to, passed by reference.
	 * @param string $section The section to retrieve the objects from.
	 * @param string $key Within the section, the key to retrieve the objects from.
	 *
	 * @return array Array of objects found.
	 */
	private function get_from_cache( array &$cache, string $section, string $key ): array
	{
		return isset( $cache[ $section ][ $key ] ) ? $cache[ $section ][ $key ] : array();
	}

	/**
	 * Save a document to the document cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $section The section of the cache to add the document to.
	 * @param string $key Within the section, the key to store the document under.
	 * @param Document $data The document to be stored.
	 */
	public function save_to_document_cache( string $section, string $key, Document $data )
	{
		$this->save_to_cache( $this->document_cache, $section, $key, $data );
	}

	/**
	 * Retrieve a set of documents from the cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $section The section to retrieve the documents from.
	 * @param string $key Within the section, the key to retrieve the documents from.
	 *
	 * @return Document[] Array of documents found.
	 */
	public function get_from_document_cache( string $section, string $key )
	{
		return $this->get_from_cache( $this->document_cache, $section, $key );
	}

	/**
	 * Save a domain to the domain cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $section The section of the cache to add the domain to.
	 * @param string $key Within the section, the key to store the domain under.
	 * @param Domain $data The domain to be stored.
	 */
	public function save_to_domain_cache( string $section, string $key, Domain $data )
	{
		$this->save_to_cache( $this->domain_cache, $section, $key, $data );
	}

	/**
	 * Retrieve a set of domains from the cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $section The section to retrieve the domains from.
	 * @param string $key Within the section, the key to retrieve the domains from.
	 *
	 * @return Domain[] Array of domains found.
	 */
	public function get_from_domain_cache( string $section, string $key )
	{
		return $this->get_from_cache( $this->domain_cache, $section, $key );
	}

	/**
	 * Save a user to the user cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $section The section of the cache to add the user to.
	 * @param string $key Within the section, the key to store the user under.
	 * @param User $data The user to be stored.
	 */
	public function save_to_user_cache( string $section, string $key, User $data )
	{
		$this->save_to_cache( $this->user_cache, $section, $key, $data );
	}

	/**
	 * Retrieve a set of users from the cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $section The section to retrieve the users from.
	 * @param string $key Within the section, the key to retrieve the users from.
	 *
	 * @return User[] Array of users found.
	 */
	public function get_from_user_cache( string $section, string $key )
	{
		return $this->get_from_cache( $this->user_cache, $section, $key );
	}

	/**
	 * Getter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Key.
	 *
	 * @return mixed Value.
	 */
	public function __get( $key )
	{
		if ( $key === 'database' )
		{
			return $this->database;
		}

		if ( $key === 'genesis_domain' )
		{
			return $this->genesis_domain;
		}

		if ( $key === 'genesis_domain_data' )
		{
			return $this->genesis_domain_data;
		}

		if ( $key === 'genesis_user_data' )
		{
			return $this->genesis_user_data;
		}

		if ( $key === 'current_domain' )
		{
			return $this->current_domain;
		}

		if ( $key === 'current_user' )
		{
			return $this->current_user;
		}
	}

	/**
	 * The callback method for when the current domain needs to return a 404 error.
	 * If there is no current domain, a default callback is used.
	 *
	 * @since 1.0.0
	 */
	public function error_404()
	{
		if ( isset( $this->current_domain ) )
		{
			$this->current_domain->error_404();
		}
		else
		{
			echo 'Error 404';
		}
	}

	/**
	 * This method routes the request and echoes the output.
	 * It always calls `exit`, meaning that any code following a call to this method will not get evaluated.
	 *
	 * @since 1.0.0
	 */
	public function route()
	{
		$parts = explode( '/', ltrim( $_SERVER['REQUEST_URI'], '/' ), 2 );

		if ( isset( $parts[0] ) )
		{
			$request = isset( $parts[1] ) ? '/' . $parts[1] : '/';
			$type = $this->get_type( $parts[0] );

			if ( isset( $type ) )
			{
				$type->render( $this, $request );
				exit;
			}
		}

		$this->error_404();
		exit;
	}
}
