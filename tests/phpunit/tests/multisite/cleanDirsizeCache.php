<?php

if ( is_multisite() ) :

	/**
	 * Tests specific to the directory size caching in multisite.
	 *
	 * @ticket 19879
	 * @group multisite
	 */
	class Tests_Multisite_Dirsize_Cache extends WP_UnitTestCase {
		protected $suppress = false;

		function setUp() {
			global $wpdb;
			parent::setUp();
			$this->suppress = $wpdb->suppress_errors();
		}

		function tearDown() {
			global $wpdb;
			$wpdb->suppress_errors( $this->suppress );
			parent::tearDown();
		}

		/**
		 * Test whether dirsize_cache values are used correctly with a more complex dirsize cache mock.
		 *
		 * @ticket 19879
		 */
		function test_get_dirsize_cache_in_recurse_dirsize_mock() {
			$blog_id = self::factory()->blog->create();
			switch_to_blog( $blog_id );

			/*
			 * Our comparison of space relies on an initial value of 0. If a previous test has failed
			 * or if the `src` directory already contains a directory with site content, then the initial
			 * expectation will be polluted. We create sites until an empty one is available.
			 */
			while ( 0 !== get_space_used() ) {
				restore_current_blog();
				$blog_id = self::factory()->blog->create();
				switch_to_blog( $blog_id );
			}

			// Clear the dirsize cache.
			delete_transient( 'dirsize_cache' );

			// Set the dirsize cache to our mock.
			set_transient( 'dirsize_cache', $this->_get_mock_dirsize_cache_for_site( $blog_id ) );

			$upload_dir = wp_upload_dir();

			// Check recurse_dirsize() against the mock. The cache should match.
			$this->assertSame( 21, recurse_dirsize( $upload_dir['basedir'] . '/2/1' ) );
			$this->assertSame( 22, recurse_dirsize( $upload_dir['basedir'] . '/2/2' ) );
			$this->assertSame( 2, recurse_dirsize( $upload_dir['basedir'] . '/2' ) );
			$this->assertSame( 11, recurse_dirsize( $upload_dir['basedir'] . '/1/1' ) );
			$this->assertSame( 12, recurse_dirsize( $upload_dir['basedir'] . '/1/2' ) );
			$this->assertSame( 13, recurse_dirsize( $upload_dir['basedir'] . '/1/3' ) );
			$this->assertSame( 1, recurse_dirsize( $upload_dir['basedir'] . '/1' ) );
			$this->assertSame( 42, recurse_dirsize( $upload_dir['basedir'] . '/custom_directory' ) );

			// No cache match, upload directory should be empty and return 0.
			$this->assertSame( 0, recurse_dirsize( $upload_dir['basedir'] ) );

			// No cache match on non existing directory should return false.
			$this->assertSame( false, recurse_dirsize( $upload_dir['basedir'] . '/does_not_exist' ) );

			// Cleanup.
			$this->remove_added_uploads();
			restore_current_blog();
		}

		/**
		 * Test whether the dirsize_cache invalidation works given a file path as input.
		 *
		 * @ticket 19879
		 */
		function test_clean_dirsize_cache_file_input_mock() {
			$blog_id = self::factory()->blog->create();
			switch_to_blog( $blog_id );

			/*
			 * Our comparison of space relies on an initial value of 0. If a previous test has failed
			 * or if the `src` directory already contains a directory with site content, then the initial
			 * expectation will be polluted. We create sites until an empty one is available.
			 */
			while ( 0 !== get_space_used() ) {
				restore_current_blog();
				$blog_id = self::factory()->blog->create();
				switch_to_blog( $blog_id );
			}

			$upload_dir       = wp_upload_dir();
			$cache_key_prefix = untrailingslashit( str_replace( ABSPATH, '', $upload_dir['basedir'] ) );

			// Clear the dirsize cache.
			delete_transient( 'dirsize_cache' );

			// Set the dirsize cache to our mock.
			set_transient( 'dirsize_cache', $this->_get_mock_dirsize_cache_for_site( $blog_id ) );

			$this->assertSame( true, array_key_exists( $cache_key_prefix . '/1/1', get_transient( 'dirsize_cache' ) ) );
			$this->assertSame( true, array_key_exists( $cache_key_prefix . '/2/1', get_transient( 'dirsize_cache' ) ) );
			$this->assertSame( true, array_key_exists( $cache_key_prefix . '/2', get_transient( 'dirsize_cache' ) ) );

			// Invalidation should also respect the directory tree up.
			// Should work fine with path to directory OR file.
			clean_dirsize_cache( $upload_dir['basedir'] . '/2/1/file.dummy' );

			$this->assertSame( false, array_key_exists( $cache_key_prefix . '/2/1', get_transient( 'dirsize_cache' ) ) );
			$this->assertSame( false, array_key_exists( $cache_key_prefix . '/2', get_transient( 'dirsize_cache' ) ) );

			// Other cache paths should not be invalidated.
			$this->assertSame( true, array_key_exists( $cache_key_prefix . '/1/1', get_transient( 'dirsize_cache' ) ) );

			// Cleanup.
			$this->remove_added_uploads();
			restore_current_blog();
		}

		/**
		 * Test whether the dirsize_cache invalidation works given a directory path as input.
		 *
		 * @ticket 19879
		 */
		function test_clean_dirsize_cache_folder_input_mock() {
			$blog_id = self::factory()->blog->create();
			switch_to_blog( $blog_id );

			/*
			 * Our comparison of space relies on an initial value of 0. If a previous test has failed
			 * or if the `src` directory already contains a directory with site content, then the initial
			 * expectation will be polluted. We create sites until an empty one is available.
			 */
			while ( 0 !== get_space_used() ) {
				restore_current_blog();
				$blog_id = self::factory()->blog->create();
				switch_to_blog( $blog_id );
			}

			$upload_dir       = wp_upload_dir();
			$cache_key_prefix = untrailingslashit( str_replace( ABSPATH, '', $upload_dir['basedir'] ) );

			// Clear the dirsize cache.
			delete_transient( 'dirsize_cache' );

			// Set the dirsize cache to our mock.
			set_transient( 'dirsize_cache', $this->_get_mock_dirsize_cache_for_site( $blog_id ) );

			$this->assertSame( true, array_key_exists( $cache_key_prefix . '/1/1', get_transient( 'dirsize_cache' ) ) );
			$this->assertSame( true, array_key_exists( $cache_key_prefix . '/2/1', get_transient( 'dirsize_cache' ) ) );
			$this->assertSame( true, array_key_exists( $cache_key_prefix . '/2', get_transient( 'dirsize_cache' ) ) );

			// Invalidation should also respect the directory tree up.
			// Should work fine with path to directory OR file.
			clean_dirsize_cache( $upload_dir['basedir'] . '/2/1' );

			$this->assertSame( false, array_key_exists( $cache_key_prefix . '/2/1', get_transient( 'dirsize_cache' ) ) );
			$this->assertSame( false, array_key_exists( $cache_key_prefix . '/2', get_transient( 'dirsize_cache' ) ) );

			// Other cache paths should not be invalidated.
			$this->assertSame( true, array_key_exists( $cache_key_prefix . '/1/1', get_transient( 'dirsize_cache' ) ) );

			// Cleanup.
			$this->remove_added_uploads();
			restore_current_blog();
		}

		/**
		 * Test whether dirsize_cache values are used correctly with a simple real upload.
		 *
		 * @ticket 19879
		 */
		function test_get_dirsize_cache_in_recurse_dirsize_upload() {
			$blog_id = self::factory()->blog->create();
			switch_to_blog( $blog_id );

			/*
			 * Our comparison of space relies on an initial value of 0. If a previous test has failed
			 * or if the `src` directory already contains a directory with site content, then the initial
			 * expectation will be polluted. We create sites until an empty one is available.
			 */
			while ( 0 !== get_space_used() ) {
				restore_current_blog();
				$blog_id = self::factory()->blog->create();
				switch_to_blog( $blog_id );
			}

			// Clear the dirsize cache.
			delete_transient( 'dirsize_cache' );

			$upload_dir = wp_upload_dir();

			$this->assertSame( 0, recurse_dirsize( $upload_dir['path'] ) );

			// Upload a file to the new site using wp_upload_bits().
			$filename = __FUNCTION__ . '.jpg';
			$contents = __FUNCTION__ . '_contents';
			$file     = wp_upload_bits( $filename, null, $contents );

			$calc_size = recurse_dirsize( $upload_dir['path'] );
			$size      = filesize( $file['file'] );
			$this->assertSame( $size, $calc_size );

			// `dirsize_cache` should now be filled after upload and recurse_dirsize() call.
			$cache_path = untrailingslashit( str_replace( ABSPATH, '', $upload_dir['path'] ) );
			$this->assertSame( true, is_array( get_transient( 'dirsize_cache' ) ) );
			$this->assertSame( $size, get_transient( 'dirsize_cache' )[ $cache_path ] );

			// Cleanup.
			$this->remove_added_uploads();
			restore_current_blog();
		}

		/**
		 * Test whether the filter to calculate space for an existing directory works as expected.
		 *
		 * @ticket 19879
		 */
		function test_pre_recurse_dirsize_filter() {
			add_filter( 'pre_recurse_dirsize', array( $this, '_filter_pre_recurse_dirsize' ) );

			$upload_dir = wp_upload_dir();
			$this->assertSame( 1042, recurse_dirsize( $upload_dir['path'] ) );

			remove_filter( 'pre_recurse_dirsize', array( $this, '_filter_pre_recurse_dirsize' ) );
		}

		function _filter_pre_recurse_dirsize() {
			return 1042;
		}

		function _get_mock_dirsize_cache_for_site( $site_id ) {
			return array(
				"wp-content/uploads/sites/$site_id/2/2" => 22,
				"wp-content/uploads/sites/$site_id/2/1" => 21,
				"wp-content/uploads/sites/$site_id/2"   => 2,
				"wp-content/uploads/sites/$site_id/1/3" => 13,
				"wp-content/uploads/sites/$site_id/1/2" => 12,
				"wp-content/uploads/sites/$site_id/1/1" => 11,
				"wp-content/uploads/sites/$site_id/1"   => 1,
				"wp-content/uploads/sites/$site_id/custom_directory" => 42,
			);
		}
	}

endif;
