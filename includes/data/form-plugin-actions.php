<?php
/**
 * Where each form plugin's "create a new form" screen lives.
 *
 * Every URL below was read out of the plugin's own source - the add_menu_page()
 * and add_submenu_page() calls that register the screen, plus the routing code
 * that decides what the extra query args do - rather than guessed or recalled.
 * The array key is the plugin's DIRECTORY name (what you see in wp-content/plugins),
 * because that is what gets matched at runtime; it is not always the wordpress.org
 * download slug. The value is a relative admin path meant to be handed straight to
 * admin_url(), so it carries no leading slash.
 *
 * Plugins whose builder cannot be opened by a plain URL - the ones that need a JS
 * modal, a POST, or a nonce - are deliberately left out. An entry that dropped the
 * user on the wrong screen would be worse than having no entry at all, so those
 * plugins simply get no link.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return [
	// Contact Form 7 6.1.7: add_submenu_page( 'wpcf7', ..., 'wpcf7-new', 'wpcf7_admin_add_new_page' ) in admin/admin.php.
	'contact-form-7'          => 'admin.php?page=wpcf7-new',
	// WPForms Lite 2.0.1.1: 'Add New Form' submenu registers 'wpforms-builder' in includes/admin/class-menu.php;
	//     the builder defaults to the 'setup' (template picker) view when no form_id is present
	//     (includes/admin/builder/class-builder.php) and src/Admin/Forms/Page.php spells out view=setup.
	'wpforms-lite'            => 'admin.php?page=wpforms-builder&view=setup',
	// Ninja Forms 3.15.1: includes/Admin/Menus/Forms.php display() treats form_id=new as a brand new
	//     temporary form and renders the builder template instead of the dashboard.
	'ninja-forms'             => 'admin.php?page=ninja-forms&form_id=new',
	// Formidable Forms 6.34: FrmFormTemplatesController::menu() registers the 'formidable-form-templates'
	//     submenu (classes/controllers/FrmFormTemplatesController.php); it is also the new_link used by
	//     the forms list in classes/views/frm-forms/list.php. FrmFormsController::route() has no 'new' action.
	'formidable'              => 'admin.php?page=formidable-form-templates',
	// Forminator 1.57.2: Forminator_CForm_New_Page is registered on the 'forminator-cform-wizard' slug in
	//     library/modules/custom-forms/admin/admin-loader.php; with no id in the query it titles itself
	//     'New Form' and opens the blank wizard (library/modules/custom-forms/admin/admin-page-new.php).
	'forminator'              => 'admin.php?page=forminator-cform-wizard',
	// Fluent Forms 6.2.13: the 'New Form' submenu slug is literally 'fluent_forms#add=1'
	//     (app/Modules/Registerer/Menu.php) and the same URL is used for the admin bar link in
	//     app/Modules/Registerer/AdminBar.php; the app reads the hash on load and opens the new form screen.
	'fluentform'              => 'admin.php?page=fluent_forms#add=1',
	// Everest Forms 3.6.0: the builder submenu is 'evf-builder' (includes/admin/class-evf-admin-menus.php)
	//     and includes/admin/class-evf-admin-forms.php branches on create-form to render the template
	//     chooser instead of the forms list; it is the plugin's own 'Add New' button target.
	'everest-forms'           => 'admin.php?page=evf-builder&create-form=1',
	// HappyForms 1.26.15: the builder is the WP Customizer in "HappyForms mode". core/classes/class-happyforms-core.php
	//     is_customize_mode() requires happyforms + form_id, and the 'Add New' submenu uses
	//     happyforms_get_form_edit_link( 0 ) which builds exactly this URL in core/helpers/helper-misc.php.
	'happyforms'              => 'customize.php?happyforms=1&form_id=0#build',
	// WS Form LITE 1.12.6: 'Add Form' submenu registers WS_FORM_NAME . '-add' in admin/class-ws-form-admin.php;
	//     WS_FORM_NAME is 'ws-form' (ws-form.php) and the plugin's own Add New buttons point here.
	'ws-form'                 => 'admin.php?page=ws-form-add',
	// Kali Forms 2.4.24: forms are the 'kaliforms_forms' post type registered with show_ui in
	//     Inc/Backend/Posts/class-forms.php, which also enqueues the builder metabox on the post screen,
	//     so WordPress's own add-new URL for that type opens the builder.
	'kali-forms'              => 'post-new.php?post_type=kaliforms_forms',
	// ARForms Lite 1.7.3: the 'Add New Form' submenu slug is 'ARForms&arfaction=new&isp=1'
	//     (core/classes/class.arforms_form_builder.php); arforms_router() hands off to
	//     arfliteformcontroller::arfliteroute(), which maps arfaction=new to arflite_new_form().
	'arforms-form-builder'    => 'admin.php?page=ARForms&arfaction=new&isp=1',
	// Bit Form 3.2.2: the 'Form Templates' submenu and admin bar node both point at this hash route in
	//     includes/Admin/Admin_Bar.php; it is where a new form is started from a template or a blank form.
	'bit-form'                => 'admin.php?page=bitform#/form-templates',
	// Visual Form Builder 3.1: 'Add New' submenu registers 'vfb-add-new' with the add_new_form() callback
	//     in admin/class-admin-menu.php.
	'visual-form-builder'     => 'admin.php?page=vfb-add-new',
];
