<?php
/**
 * Plugin Name: GitHub Repo Widget
 * Description: Displays a GitHub user's public repositories via a widget. Clean and simple.
 * Version: 1.0
 * Author: Damon Noisette
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GitHub_Repo_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'github_repo_widget',
            'GitHub Repo Widget',
            ['description' => 'Displays repositories from a GitHub user.']
        );
        add_action( 'wp_enqueue_scripts', [$this, 'add_styles'] );
    }

    public function add_styles() {
        wp_register_style( 'gh-repo-widget-style', plugin_dir_url(__FILE__) . 'gh-repo-widget.css' );
        wp_enqueue_style( 'gh-repo-widget-style' );
    }

    public function widget( $args, $instance ) {
        $url = isset($instance['github_url']) ? esc_url($instance['github_url']) : '';
        $username = $this->extract_username( $url );

        echo $args['before_widget'];
        if ( !empty($instance['title']) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }

        if ( $username ) {
            echo $this->render_repos( $username );
        } else {
            echo '<p>Invalid GitHub URL.</p>';
        }

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : 'GitHub Repositories';
        $github_url = isset( $instance['github_url'] ) ? esc_url( $instance['github_url'] ) : '';
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
                   value="<?php echo $github_url; ?>" placeholder="https://github.com/username">
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $instance = [];
        $instance['title'] = sanitize_text_field( $new_instance['title'] );
        $instance['github_url'] = esc_url_raw( $new_instance['github_url'] );
        return $instance;
    }

    private function extract_username( $url ) {
        if ( preg_match( '#github\.com/([\w-]+)#', $url, $matches ) ) {
            return $matches[1];
        }
        return '';
    }

    private function fetch_repos( $username ) {
        $endpoint = "https://api.github.com/users/$username/repos";
        $response = wp_remote_get( $endpoint, [
            'headers' => ['User-Agent' => 'WordPress'],
            'timeout' => 10
        ]);
        if ( is_wp_error( $response ) ) return [];
        if ( wp_remote_retrieve_response_code($response) !== 200 ) return [];
        return json_decode( wp_remote_retrieve_body($response), true );
    }

    private function render_repos( $username ) {
        $repos = $this->fetch_repos( $username );
        if ( empty( $repos ) ) return '<p>No repositories found.</p>';

        usort($repos, function($a, $b) {
            return strcmp($b['updated_at'], $a['updated_at']);
        });

        $output = '<ul class="gh-repo-list">';
        foreach ( $repos as $repo ) {
            $name = esc_html($repo['name']);
            $desc = esc_html($repo['description'] ?? '');
            $url = esc_url($repo['html_url']);
            $stars = intval($repo['stargazers_count']);
            $updated = date_i18n(get_option('date_format'), strtotime($repo['updated_at']));

            $output .= "<li class='gh-repo-item'>";
            $output .= "<a class='repo-link' href='$url' target='_blank'><strong>$name</strong></a>";
            if ($desc) $output .= "<p class='repo-desc'>$desc</p>";
            $output .= "<div class='repo-meta'>⭐ $stars | Updated: $updated</div>";
            $output .= "</li>";
        }
        $output .= '</ul>';
        return $output;
    }
}

function register_github_repo_widget() {
    register_widget( 'GitHub_Repo_Widget' );
}
add_action( 'widgets_init', 'register_github_repo_widget' );
