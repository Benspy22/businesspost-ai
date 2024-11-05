<?php
/*
Plugin Name: BusinessPost-AI
Description: Générateur de posts adaptés pour les réseaux sociaux, basé sur les secteurs d'activité.
Version: 1.1
Author: Benjamin de Bruijne
License: GPL2
Text Domain: businesspost-ai
*/

// Sécurité pour empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Constantes pour la version et le dossier du plugin
define('BPAI_VERSION', '1.0');
define('BPAI_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Création de la table de configuration à l'activation du plugin
function bpai_create_config_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'businesspost_ai_config';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        sector varchar(255) NOT NULL,
        structure_name varchar(255) NOT NULL,
        structure_info text NOT NULL,
        social_networks text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'bpai_create_config_table');

// Enqueue le fichier CSS
function bpai_enqueue_styles() {
    wp_enqueue_style(
        'bpai-styles',
        plugins_url('css/style.css', __FILE__),
        array(),
        BPAI_VERSION
    );
}
add_action('admin_enqueue_scripts', 'bpai_enqueue_styles');
add_action('wp_enqueue_scripts', 'bpai_enqueue_styles');

// Fonction pour afficher le formulaire de configuration lors de la première activation
function bpai_render_configuration_form() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'businesspost_ai_config';

    // Vérifie si une configuration existe déjà
    $config = $wpdb->get_row("SELECT * FROM $table_name LIMIT 1");
    
    // Si la configuration n'existe pas, afficher le formulaire
    if (!$config) {
        ob_start();
        ?>
        <h2>Configuration de BusinessPost-AI</h2>
        <form id="bpai-config-form" method="post" action="">
            <label>Secteur d'activité :</label>
            <input type="text" name="sector" required>

            <label>Nom de la structure :</label>
            <input type="text" name="structure_name" required>

            <label>Informations utiles :</label>
            <textarea name="structure_info" required></textarea>

            <label>Réseaux sociaux ciblés :</label>
            <div class="bpai-checkboxes">
                <label><input type="checkbox" name="social_networks[]" value="Facebook"> Facebook</label>
                <label><input type="checkbox" name="social_networks[]" value="Instagram"> Instagram</label>
                <label><input type="checkbox" name="social_networks[]" value="Twitter"> Twitter</label>
                <label><input type="checkbox" name="social_networks[]" value="LinkedIn"> LinkedIn</label>
                <label><input type="checkbox" name="social_networks[]" value="TikTok"> TikTok</label>
            </div>

            <button type="submit">Enregistrer la configuration</button>
        </form>
        <?php
        return ob_get_clean();
    } else {
        return "<p>La configuration a déjà été effectuée. Utilisez le bouton de réinitialisation si nécessaire.</p>";
    }
}

// Shortcode pour afficher le formulaire de configuration [bpai_config]
add_shortcode('bpai_config', 'bpai_render_configuration_form');

// Fonction pour gérer la soumission du formulaire de configuration
function bpai_handle_config_submission() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'businesspost_ai_config';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sector'], $_POST['structure_name'], $_POST['structure_info'], $_POST['social_networks'])) {
        // Valider et sécuriser les données
        $sector = sanitize_text_field($_POST['sector']);
        $structure_name = sanitize_text_field($_POST['structure_name']);
        $structure_info = sanitize_textarea_field($_POST['structure_info']);
        $social_networks = implode(', ', array_map('sanitize_text_field', $_POST['social_networks']));

        // Insérer les données dans la table de configuration
        $wpdb->insert(
            $table_name,
            [
                'sector' => $sector,
                'structure_name' => $structure_name,
                'structure_info' => $structure_info,
                'social_networks' => $social_networks,
            ],
            [
                '%s', '%s', '%s', '%s'
            ]
        );

        // Rediriger vers la même page pour éviter le rechargement de la soumission
        wp_redirect(add_query_arg('config_saved', 'true'));
        exit;
    }
}
add_action('wp', 'bpai_handle_config_submission');

// Fonction pour réinitialiser la configuration
function bpai_reset_configuration() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'businesspost_ai_config';

    // Supprime toutes les données de configuration
    $wpdb->query("DELETE FROM $table_name");

    // Redirige vers la même page pour éviter le rechargement de la soumission
    wp_redirect(add_query_arg('config_reset', 'true'));
    exit;
}

// Shortcode pour ajouter un bouton de réinitialisation dans une page
function bpai_render_reset_button() {
    if (isset($_POST['bpai_reset_config'])) {
        bpai_reset_configuration();
    }
    ?>
    <form method="post" action="">
        <button type="submit" name="bpai_reset_config" class="button">Réinitialiser la configuration</button>
    </form>
    <?php
}
add_shortcode('bpai_reset_button', 'bpai_render_reset_button');

// Affichage de notifications après redirection
function bpai_show_admin_notices() {
    if (isset($_GET['config_saved']) && $_GET['config_saved'] == 'true') {
        echo '<div class="notice notice-success"><p>Configuration enregistrée avec succès !</p></div>';
    }
    if (isset($_GET['config_reset']) && $_GET['config_reset'] == 'true') {
        echo '<div class="notice notice-success"><p>La configuration a été réinitialisée avec succès !</p></div>';
    }
}
add_action('admin_notices', 'bpai_show_admin_notices');

// Ajout du menu dans l'administration WordPress
function bpai_add_admin_menu() {
    add_menu_page(
        'BusinessPost-AI',
        'BusinessPost-AI',
        'manage_options',
        'businesspost-ai',
        'bpai_display_main_page',
        'dashicons-share',
        30
    );
    
    add_submenu_page(
        'businesspost-ai',
        'Paramètres API',
        'Paramètres API',
        'manage_options',
        'businesspost-ai-settings',
        'bpai_display_settings_page'
    );
}
add_action('admin_menu', 'bpai_add_admin_menu');

// Page des paramètres API
function bpai_display_settings_page() {
    // Sauvegarder la clé API
    if (isset($_POST['bpai_api_key'])) {
        update_option('bpai_openai_api_key', sanitize_text_field($_POST['bpai_api_key']));
        echo '<div class="notice notice-success"><p>Clé API sauvegardée avec succès!</p></div>';
    }

    // Afficher le formulaire
    ?>
    <div class="wrap">
        <h1>Paramètres API BusinessPost-AI</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bpai_api_key">Clé API OpenAI</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="bpai_api_key" 
                               name="bpai_api_key" 
                               value="<?php echo esc_attr(get_option('bpai_openai_api_key')); ?>"
                               class="regular-text">
                        <p class="description">
                            Entrez votre clé API OpenAI. Vous pouvez l'obtenir sur 
                            <a href="https://platform.openai.com/api-keys" target="_blank">
                                le site d'OpenAI
                            </a>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Sauvegarder la clé API'); ?>
        </form>
    </div>
    <?php
}
// Page principale du plugin avec le nouveau formulaire
function bpai_display_main_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'businesspost_ai_config';
    $config = $wpdb->get_row("SELECT * FROM $table_name LIMIT 1");

    if (!$config) {
        echo '<div class="notice notice-error"><p>Veuillez d\'abord configurer votre entreprise via le formulaire de configuration. Il vous suffit de mettre le shortcode [bpai_config] sur la page de votre choix. Pour faire un reset, mettez sur une autre page le shortcode [bpai_reset_button] et cliquer sur: Réinitialiser la configuration.</p></div>';
        return;
    }

    if (!get_option('bpai_openai_api_key')) {
        echo '<div class="notice notice-error"><p>Veuillez d\'abord configurer votre clé API OpenAI dans les paramètres.</p></div>';
        return;
    }

    ?>
    <div class="wrap">
        <h1>Générateur de Posts</h1>
        
        <div class="bpai-generator-form">
            <form method="post" action="">
                <div class="form-group">
                    <label for="post_subject">Sujet du post : <span class="required">*</span></label>
                    <input type="text" 
                           id="post_subject" 
                           name="post_subject" 
                           placeholder="Ex: Match de ce weekend contre l'équipe de Soignies" 
                           required>
                    <p class="description">Décrivez le sujet principal de votre post. Soyez aussi précis que possible.</p>
                </div>

                <div class="form-group">
                    <label for="tone">Ton du message : <span class="required">*</span></label>
                    <select id="tone" name="tone" required>
                        <option value="">-- Sélectionnez un ton --</option>
                        <option value="professionnel">Professionnel</option>
                        <option value="decontracte">Décontracté</option>
                        <option value="humoristique">Humoristique</option>
                        <option value="formel">Formel</option>
                        <option value="engage">Engagé</option>
                        <option value="inspirant">Inspirant</option>
                    </select>
                    <p class="description">Choisissez le ton qui convient le mieux à votre message.</p>
                </div>

                <div class="form-group">
                    <label for="length">Longueur souhaitée :</label>
                    <select id="length" name="length">
                        <option value="court">Court - Environ 50 mots</option>
                        <option value="moyen" selected>Moyen - Environ 100 mots</option>
                        <option value="long">Long - Environ 200 mots</option>
                    </select>
                    <p class="description">La longueur approximative du post généré.</p>
                </div>

                <div class="form-group">
                    <label for="hashtags">Inclure des hashtags :</label>
                    <select id="hashtags" name="hashtags">
                        <option value="none">Aucun hashtag</option>
                        <option value="few" selected>Quelques hashtags (2-3)</option>
                        <option value="many">Plusieurs hashtags (4-6)</option>
                    </select>
                    <p class="description">Le nombre de hashtags à inclure dans le post.</p>
                </div>

                <div class="form-group">
                    <label for="social_network">Réseau social : <span class="required">*</span></label>
                    <select id="social_network" name="social_network" required>
                        <?php
                        $networks = explode(', ', $config->social_networks);
                        foreach ($networks as $network) {
                            echo '<option value="' . esc_attr($network) . '">' . esc_html($network) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" name="generate_post" class="button button-primary">
                        Générer le post
                    </button>
                    <button type="reset" class="button">
                        Réinitialiser
                    </button>
                </div>
            </form>
        </div>

        <?php
        if (isset($_POST['generate_post'])) {
            bpai_generate_social_post(
                $config, 
                $_POST['social_network'], 
                $_POST['post_subject'],
                $_POST['tone'],
                $_POST['length'],
                $_POST['hashtags']
            );
        }
        ?>
    </div>
    <?php
}

// Fonction pour générer un post via l'API OpenAI
function bpai_generate_social_post($config, $social_network, $subject, $tone, $length, $hashtags) {
    $api_key = get_option('bpai_openai_api_key');
    
    // Construire le prompt en fonction des paramètres
    $prompt = "En tant qu'expert en marketing digital pour une entreprise du secteur {$config->sector}, ";
    $prompt .= "crée un post pour {$social_network} sur le sujet suivant : {$subject}. ";
    $prompt .= "Nom de l'entreprise : {$config->structure_name}. ";
    $prompt .= "Informations sur l'entreprise : {$config->structure_info}. ";
    $prompt .= "Utilise un ton {$tone}. ";

    // Ajout des contraintes de longueur
    switch ($length) {
        case 'court':
            $prompt .= "Le texte doit faire environ 50 mots. ";
            break;
        case 'moyen':
            $prompt .= "Le texte doit faire environ 100 mots. ";
            break;
        case 'long':
            $prompt .= "Le texte doit faire environ 200 mots. ";
            break;
    }

    // Ajout des contraintes de hashtags
    switch ($hashtags) {
        case 'few':
            $prompt .= "Ajoute 2-3 hashtags pertinents à la fin. ";
            break;
        case 'many':
            $prompt .= "Ajoute 4-6 hashtags pertinents à la fin. ";
            break;
        case 'none':
            $prompt .= "N'ajoute pas de hashtags. ";
            break;
    }
    
    // Ajouter des instructions spécifiques selon le réseau social
    switch ($social_network) {
        case 'Twitter':
            $prompt .= "Le post doit faire maximum 280 caractères.";
            break;
        case 'Instagram':
            $prompt .= "Adapte le style pour Instagram.";
            break;
        case 'LinkedIn':
            $prompt .= "Adopte un ton professionnel adapté à LinkedIn.";
            break;
        case 'Facebook':
            $prompt .= "Adapte le style pour Facebook.";
            break;
        case 'TikTok':
            $prompt .= "Adopte un ton dynamique et jeune adapté à TikTok.";
            break;
    }

    // Appel à l'API OpenAI
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7
        ])
    ]);

    if (is_wp_error($response)) {
        echo '<div class="notice notice-error"><p>Erreur : ' . esc_html($response->get_error_message()) . '</p></div>';
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response));
    
    if (isset($body->error)) {
        echo '<div class="notice notice-error"><p>Erreur API : ' . esc_html($body->error->message) . '</p></div>';
        return;
    }

    // Afficher le résultat
    $generated_text = $body->choices[0]->message->content;
    ?>
    <div class="bpai-generated-post">
        <h3>Post généré pour <?php echo esc_html($social_network); ?></h3>
        <div class="post-content">
            <?php echo nl2br(esc_html($generated_text)); ?>
        </div>
        <button class="button copy-button" onclick="navigator.clipboard.writeText('<?php echo esc_js($generated_text); ?>')">
            Copier le texte
        </button>
    </div>
    <?php
}
?>