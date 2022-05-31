<?php

namespace Multipress;

/**
 * Core interface to be implemented by databases.
 *
 * @since 1.0.0
 */
interface Database
{
	/**
	 * Query the database for documents of a particular type.
	 *
	 * @since 1.0.0
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
	public function get_documents_by_type( string $type ): array;

	/**
	 * Query the database for domain(s) of a particular ID.
	 *
	 * @since 1.0.0
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
	public function get_domain_by_id( int $id ): array;

	/**
	 * Query the database for domain(s) of a particular hostname.
	 *
	 * @since 1.0.0
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
	public function get_domain_by_name( string $name ): array;

	/**
	 * Query the database for user(s) of a particular ID.
	 *
	 * @since 1.0.0
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
	public function get_user_by_id( int $id ): array;

	/**
	 * Insert a new document into the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $changes Initial data to apply to the document.
	 *
	 * @return int The ID of the document.
	 */
	public function insert_document( array $changes ): int;

	/**
	 * Insert a new domain into the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $changes Initial data to apply to the domain.
	 *
	 * @return int The ID of the domain.
	 */
	public function insert_domain( array $changes ): int;

	/**
	 * Insert a new user into the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $changes Initial data to apply to the user.
	 *
	 * @return int The ID of the user.
	 */
	public function insert_user( array $changes ): int;

	/**
	 * Update a document in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Document ID.
	 * @param array $changes Changes to apply to the document.
	 */
	public function update_document( int $id, array $changes );

	/**
	 * Update a domain in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Domain ID.
	 * @param array $changes Changes to apply to the domain.
	 */
	public function update_domain( int $id, array $changes );

	/**
	 * Update a user in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id User ID.
	 * @param array $changes Changes to apply to the user.
	 */
	public function update_user( int $id, array $changes );

	/**
	 * Delete the document with a particular ID from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Document ID.
	 */
	public function delete_document( int $id );

	/**
	 * Delete the domain with a particular ID from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Domain ID.
	 */
	public function delete_domain( int $id );

	/**
	 * Delete the user with a particular ID from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id User ID.
	 */
	public function delete_user( int $id );
}
