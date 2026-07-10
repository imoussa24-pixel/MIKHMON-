<?php
$langid="fr";
$langname = "Français";
$language = "Langue";

$_about = "À propos";
$_action = "Action";
$_add = "Ajouter";
$_add_router = "Ajouter un routeur";
$_add_user = "Ajouter un utilisateur";
$_add_user_profile = "Ajouter un profil";
$_admin = "Admin";
$_admin_settings = "Paramètres admin";
$_all = "Tous";
$_auto_reload = "Actualisation auto";
$_bluetooth_ac = "Imprimer le code d'accès BT";
$_board_name = "Nom de la carte";
$_by_comment = "Par commentaire";
$_cancel = "Annuler";
$_character = "Caractère";
$_close = "Fermer";
$_comment = "Commentaire";
$_confirm = "Confirmer";
$_connecting = "Connexion";
$_cpu_load = "Charge CPU";
$_currency = "Devise";
$_dashboard = "Tableau de bord";
$_data_limit = "Limite de données";
$_date ="Date";
$_days = "jours";
$_delete_data = "Supprimer les données";
$_delete = "Supprimer";
$_dhcp_leases = "Baux DHCP";
$_dns_name = "Nom DNS";
$_edit = "Modifier";
$_edit_user = "Modifier l'utilisateur";
$_end = "Fin";
$_expired = "Expiré";
$_expired_mode = "Mode d'expiration";
$_extend_expired_date = "Prolonger la date d'expiration";
$_format_file_name = "Format du nom de fichier";
$_free_hdd = "HDD libre";
$_free_memory = "Mémoire libre";
$_generate_code = "Générer un code";
$_generate = "Générer";
$_generate_user = "Générer des utilisateurs";
$_grace_period = "Période de grâce";
$_help = "Aide";
$_hosts = "Hôtes";
$_hotspot_active = "Hotspot actif";
$_hotspot_cookies = "Cookies";
$_hotspot_log = "Journal Hotspot";
$_hotspot_name = "Nom du Hotspot";
$_hotspot_users = "Utilisateur Hotspot";
$_hours = "heures";
$_idle_timeout = "Délai d'inactivité";
$_income = "Revenus";
$_interface = "Interface";
$_ip_bindings = "Liaisons IP";
$_last_generate = "Dernière génération";
$_list_logo = "Liste des logos";
$_live_report = "Rapport en direct";
$_loading = "Chargement";
$_loading_interface = "Chargement interface";
$_loading_theme = "Chargement du mode";
$_lock_user = "Verrouiller utilisateur";
$_log = "Journal";
$_logout = "Déconnexion";
$_messages = "Messages";
$_min = "min";
$_minutes = "minutes";
$_model = "Modèle";
$_name = "Nom";
$_no = "Non";
$_open = "Ouvrir";
$_package = "Paquet";
$_password = "Mot de passe";
$_please_login = "Veuillez vous connecter";
$_ppp_active = "PPP actif";
$_ppp_profiles = "Profils PPP";
$_ppp_secrets = "Secrets PPP";
$_prefix = "Préfixe";
$_price = "Prix";
$_print_default = "Par défaut";
$_print = "Imprimer";
$_print_qr = "QR";
$_print_small = "Petit";
$_processing = "Traitement...";
$_profile = "Profil";
$_qty = "Qté";
$_quick_print = "Impression rapide";
$_random = "Aléatoire";
$_readme = "Lisez-moi";
$_reboot = "Êtes-vous sûr de redémarrer";
$_reduce_expired_date = "Réduire la date d'expiration";
$_remove = "Supprimer";
$_report = "Rapport";
$_reset_start_date = "Réinitialiser la date de début";
$_resume = "Résumé";
$_router_list = "Liste des routeurs";
$_save = "Enregistrer";
$_search = "Rechercher";
$_sec = "sec";
$_seconds = "secondes";
$_selected = "Sélectionné";
$_select_interface = "Sélectionner une interface";
$_selling_price = "Prix de vente";
$_selling_report = "Rapport de vente";
$_send_to_WA = "Envoyer vers WhatsApp";
$_session_name = "Nom de session";
$_session = "Session";
$_session_settings = "Paramètres session";
$_settings = "Paramètres";
$_share = "Partager";
$_show_all = "Tout afficher";
$_shutdown = "Êtes-vous sûr d'arrêter";
$_start = "Début";
$_system_date_time = "Date et heure système";
$_system_off = "Arrêt";
$_system_reboot = "Redémarrer";
$_system_scheduler = "Planificateur";
$_system = "Système";
$_template_editor = "Éditeur de modèle";
$_theme = "Mode";
$_this_month = "Ce mois";
$_time_limit = "Limite de temps";
$_time = "Temps";
$_today = "Aujourd'hui";
$_total = "Total";
$_traffic_interface = "Interface trafic";
$_traffic_monitor = "Moniteur trafic";
$_traffic = "Trafic";
$_upload = "Importer";
$_upload_logo = "Importer logo";
$_uptime = "Disponibilité";
$_uptime_user = "Temps connecté";
$_user_length = "Longueur du nom";
$_user_list = "Liste utilisateurs";
$_user_log = "Journal utilisateur";
$_user_mode = "Mode utilisateur";
$_user_name = "Nom d'utilisateur";
$_user_pass = "Nom d'utilisateur & mot de passe";
$_user_profile_list = "Liste des profils";
$_user_profile = "Profil utilisateur";
$_users = "Utilisateurs";
$_user_user = "Nom d'utilisateur = mot de passe";
$_validity = "Validité";
$_voucher_code ="Code voucher";
$_vouchers = "Vouchers";
$_yes = "Oui";

//details
$_format_time_limit = '
    Format '.$_time_limit.'.<br>
    [wdhm] Exemple : 30d = 30'.$_days.', 12h = 12'.$_hours.', 4w3d = 31'.$_days.'.
';
$_details_add_user = '
    '.$_add_user.' avec '.$_time_limit.'.<br>
    '.$_time_limit.' doit être inférieure à '.$_validity.'.
';

$_details_user_profile = '
'.$_expired_mode.' contrôle les utilisateurs Hotspot.<br>
Options : Supprimer, Notification, Supprimer & Enregistrer, Notification & Enregistrer.
<ul>
<li>Supprimer : l\'utilisateur sera supprimé à expiration.</li>
<li>Notification : l\'utilisateur ne sera pas supprimé et recevra une notification après expiration.</li>
<li>Enregistrer : conserve le prix de chaque connexion utilisateur pour calculer les ventes totales hotspot.</li>
</ul>
</p>
        
        <p>'.$_lock_user.' : un seul appareil peut utiliser le même nom utilisateur.</p>
';

$_format_validity = '
Format '.$_validity.'<br>
[wdhm] Exemple : 30d = 30'.$_days.', 12h = 12'.$_hours.', 30m = 30'.$_minutes.'<br>
5'.$_hours.' 30'.$_minutes.' = 5h30m';

$_format_ip_binding = '
    Format limite max Upload/Download<br>
    [k / M] Exemple : 512k, 1500k, 1M<br><br>
    Format '.$_validity.'<br>
    [d] Exemple : 30d = 30'.$_days.'.<br>
';

$_help_report = '
<ul>
<li>Cliquer sur CSV pour télécharger le rapport.</li>
<li>Pour filtrer par mois, sélectionner le jour et le mois, puis cliquer sur Filtrer.</li>
<li>Pour filtrer par '.$_prefix.', saisir le '.$_prefix.' dans la recherche, puis cliquer sur Filtrer.</li>
<li>Pour filtrer par '.$_comment.', saisir !!'.$_comment.' dans la colonne ou cliquer sur un commentaire. (TIKRAS IT Online)</li>
<li>Il est recommandé de supprimer le rapport de vente après le téléchargement du CSV.</li>
</ul>
';

$_delete_report = '
<ul>
  <li>La suppression du rapport de vente supprimera aussi le '.$_user_log.'.</li>
  <li>Il est recommandé de télécharger le '.$_user_log.' au préalable.</li>
</ul>
';
