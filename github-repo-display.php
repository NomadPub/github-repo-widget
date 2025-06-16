<?php
/**
 * Plugin Name: GitHub Repo Display
 * Description: Displays GitHub user repositories via shortcode or widget.
 * Version: 1.0
 * Author: Damon Noisette
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GitHub_Repo_Display {
    public static function init() {
        add_shortcode( 'github_repos', [__CLASS__, 'shortcode_handler'] );
        add_action( 'wp_enqueue_scripts', [__CLASS__, 'enqueue_styles'] );
        add_action( 'widgets_init', function() {
            register_widget( 'GitHub_Repo_Widget' );
        });
    }

    public static function enqueue_styles() {
        wp_register_style( 'gh-repo-style', plugin_dir_url(__FILE__) . 'github-repo-display.css' );
        wp_enqueue_style( 'gh-repo-style' );
    }

    public static function shortcode_handler( $atts ) {
        $a = shortcode_atts([
            'url' => ''
        ], $atts);

        $username = self::extract_username( $a['url'] );
        if ( !$username ) return '<p>Invalid GitHub URL.</p>';
        return self::render_repos( $username );
    }

    public static function extract_username( $url ) {
        if ( preg_match( '#github\.com/([\w-]+)#', $url, $matches ) ) {
            return $matches[1];
        }
        return '';
    }

    public static function fetch_repos( $username ) {
        $endpoint = "https://api.github.com/users/$username/repos";
        $response = wp_remote_get( $endpoint, [
            'headers' => ['User-Agent' => 'WordPress'],
            'timeout' => 10
        ]);
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    public static function render_repos( $username ) {
        $repos = self::fetch_repos( $username );
        if ( empty($repos) ) return '<p>No repositories found.</p>';

        usort($repos, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

        $output = '<ul class="gh-repo-list">';
        foreach ( $repos as $repo ) {
            $name = esc_html($repo['name']);
            $desc = esc_html($repo['description'] ?? '');
            $url = esc_url($repo['html_url']);
            $stars = intval($repo['stargazers_count']);
            $updated = date_i18n(get_option('date_format'), strtotime($repo['updated_at']));

            $output .= "<li class='gh-repo-item'>";
            $output .= "<a class='repo-link' href='$url' target='_blank'><strong>$name</strong></a>";
            if ( $desc ) $output .= "<p class='repo-desc'>$desc</p>";
            $output .= "<div class='repo-meta'>⭐ $stars | Updated: $updated</div>";
            $output .= "</li>";
        }
        $output .= '</ul>';
        return $output;
    }
}

GitHub_Repo_Display::init();

class GitHub_Repo_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'github_repo_widget',
            'GitHub Repo Widget',
            ['description' => 'Display GitHub repositories for any user']
        );
    }

    public function widget( $args, $instance ) {
        echo $args['before_widget'];

        $title = apply_filters( 'widget_title', $instance['title'] ?? '' );
        if ( $title ) echo $args['before_title'] . $title . $args['after_title'];

        $url = esc_url( $instance['github_url'] ?? '' );
        $username = GitHub_Repo_Display::extract_username( $url );

        if ( $username ) {
            echo GitHub_Repo_Display::render_repos( $username );
        } else {
            echo '<p>Invalid GitHub URL.</p>';
        }

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = esc_attr( $instance['title'] ?? 'GitHub Repositories' );
        $url = esc_url( $instance['github_url'] ?? '' );
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" 
                   name="<?php echo $this->get_field_name('title'); ?>" type="text" 
                   value="<?php echo $title; ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('github_url'); ?>">GitHub URL:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('github_url'); ?>" 
                   name="<?php echo $this->get_field_name('github_url'); ?>" type="text" 
                   value="<?php echo $url; ?>" placeholder="https://github.com/username">
        </p>
        <?php
    }

    public function update( $new, $old ) {
        return [
            'title' => sanitize_text_field( $new['title'] ?? '' ),
            'github_url' => esc_url_raw( $new['github_url'] ?? '' )
        ];
    }
}
