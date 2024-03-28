<?php

class Notes_Module extends POS_Module {
    public $id = 'notes';
    public $name = "Notes";

    function register() {
        register_taxonomy( 'notebook', [ $this->id, 'todo' ], array(
            'label'                 => 'Notebook',
            'public'                => false,
            'hierarchical'          => true,
            'show_ui'               => true,
            'show_in_menu'          => 'personalos',
            'default_term' => [
                'name' => 'Inbox', 
                'slug' => 'inbox',
                'description' => 'Default notebook for notes and todos.',
            ],
            'show_admin_column'     => true,
            'query_var'             => true,
            'show_in_rest'          => true,
            'rest_namespace'        => $this->rest_namespace,
            'rewrite'               => array( 'slug' => 'notebook' ),
        ) );
        $this->register_post_type( [
            'taxonomies' => [ 'notebook', 'post_tag' ],
        ] );

        add_action( 'save_post_' . $this->id, array( $this, 'autopublish_drafts' ), 10, 3 );
        add_action( 'wp_dashboard_setup', array( $this,'init_admin_widgets' ) );
    }

    public function autopublish_drafts( $post_id, $post, $updating) {
        if ( $post->post_status === 'draft' ) {
            wp_publish_post( $post );
        }
    }

    public function create( $title, $content, $inbox = false ) {
        $post = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => $this->id,
        );
        $post_id = wp_insert_post( $post );
        return $post_id;
    }
    public function init_admin_widgets() {
        $terms = get_terms( [ 'taxonomy' => 'notebook', 'hide_empty' => false ] );
        foreach( $terms as $term ) {
            $this->register_notebook_admin_widget( $term );
        }
        wp_enqueue_style('pos-notes-widgets-css', plugin_dir_url( __FILE__ ) . 'admin-widgets.css' );
    }
    public function register_notebook_admin_widget( $term ) {
        wp_add_dashboard_widget(
            'pos_notebook_' . $term->slug,
            $term->name,
            array( $this,'notebook_admin_widget' ),
            null,
            $term
        );
    }
    public function notebook_admin_widget( $widget_config, $conf ) {
        $check = '<?xml version="1.0" ?><svg height="20px" version="1.1" viewBox="0 0 20 20" width="20px" xmlns="http://www.w3.org/2000/svg" xmlns:sketch="http://www.bohemiancoding.com/sketch/ns" xmlns:xlink="http://www.w3.org/1999/xlink"><title/><desc/><defs/><g fill="none" fill-rule="evenodd" id="Page-1" stroke="none" stroke-width="1"><g fill="#000000" id="Core" transform="translate(-170.000000, -86.000000)"><g id="check-circle-outline-blank" transform="translate(170.000000, 86.000000)"><path d="M10,0 C4.5,0 0,4.5 0,10 C0,15.5 4.5,20 10,20 C15.5,20 20,15.5 20,10 C20,4.5 15.5,0 10,0 L10,0 Z M10,18 C5.6,18 2,14.4 2,10 C2,5.6 5.6,2 10,2 C14.4,2 18,5.6 18,10 C18,14.4 14.4,18 10,18 L10,18 Z" id="Shape"/></g></g></g></svg>';
        $notes = get_posts( array(
            'post_type' => $this->id,
            'post_status' => [ 'publish','private' ],
            'tax_query' => [
                [
                    'taxonomy' => 'notebook',
                    'field' => 'slug',
                    'terms' => [
                        $conf['args']->slug
                    ]
                ]
            ]
        ) );
        $notes = array_filter( $notes, function( $post ) {
            return current_user_can( 'read_post', $post->ID );
        } );
        if ( count( $notes ) > 0 ) {
            echo "<h3>{$conf['args']->name}: Notes</h3>";
            $notes = array_map( function( $note ) {
                return "<li><a href='" . get_edit_post_link( $note->ID ) . "' aria-label='Edit “{$note->post_title}”'><h5>{$note->post_title}</h5><time datetime='{$note->post_date}'>" . date( 'F j, Y', strtotime( $note->post_date ) ) . "</time><p>" . get_the_excerpt( $note ) . "</p></a></li>";
            }, $notes );
    
            echo '<ul class="pos_admin_widget_notes">' . implode( '', $notes ) . '</ul>'; 
        }
        $notes = get_posts( array(
            'post_type' => 'todo',
            'post_status' => [ 'publish','private' ],
            'tax_query' => [
                [
                    'taxonomy' => 'notebook',
                    'field' => 'slug',
                    'terms' => [
                        $conf['args']->slug
                    ]
                ]
            ]
        ) );
        $notes = array_filter( $notes, function( $post ) {
            return current_user_can( 'read_post', $post->ID );
        } );
        if ( count( $notes ) > 0 ) {
            echo "<h3>{$conf['args']->name}: TODOs</h3>";
            $notes = array_map( function( $note ) use ( $check ) {
                return "<li><a href='" . esc_url( wp_nonce_url( "post.php?action=trash&amp;post=$note->ID", 'trash-post_' . $note->ID ) ) . "'>{$check}<a style='font-weight:bold;margin: 0 5px 0 0 ' href='" . get_edit_post_link( $note->ID ) . "' aria-label='Edit “{$note->post_title}”'>{$note->post_title}</a></li>";
            }, $notes );
    
            echo '<ul class ="pos_admin_widget_todos" >' . implode( '', $notes ) . '</ul>'; 
        }

        //$term = get_term_by( 'slug', $conf['args']['notebook'], 'notebook' );
        //$query = new WP_Query( array( 'post_type' => $this->id, 'tax_query' => [ [ 'taxonomy' => 'notebook', 'field' => 'slug', 'terms' => [  ] ] ] ) );
        //echo "<p>Notes in this  notebook: {$query->found_posts}</p>";
    }
}