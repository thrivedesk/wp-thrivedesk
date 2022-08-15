<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;


function load_scripts() {
    wp_enqueue_style('thrivedesk-frontend-style', THRIVEDESK_PLUGIN_ASSETS . '/css/admin.css', '', THRIVEDESK_VERSION);

    wp_enqueue_script('thrivedesk-frontend-script', THRIVEDESK_PLUGIN_ASSETS . '/js/admin.js', ['jquery'], THRIVEDESK_VERSION);
}

add_action('wp_enqueue_scripts', 'load_scripts');


/**
 * @return array
 */
function getFormProviders(): array
{
    // store the activated form providers
    $providers = [];

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_fluent_form_active()) {
        $providers['fluent-form'] = 'Fluent Form';
    }
    if (\ThriveDesk\FormProviders\FormProviderHelper::is_contact_form_plugin_active()) {
        $providers['contact-form-7'] = 'Contact Form 7';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_wp_form_plugin_active()) {
        $providers['wp-forms'] = 'WPForms';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_ninja_form_plugin_active()) {
        $providers['ninja-form'] = 'Ninja Forms';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_forminator_form_plugin_active()) {
        $providers['forminator-form'] = 'Forminator Forms';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_formidable_form_plugin_active()) {
        $providers['formidable-form'] = 'Formidable Forms';
    }

//    dd($providers);
    return $providers;
}

function getSelectedTdSettings() {
    return get_option('td_helpdesk_settings') ?? null;
}


function conversation_page($atts) {
    ob_start();
    ?>
    <div class="w-full bg-gray-200 py-6 px-8">
        <h1 class="pb-3 text-left text-lg font-extrabold">Tickets</h1>
        <div class="rounded-lg shadow-md sm:rounded-lg bg-white">
            <div class="flex justify-between items-center px-6 py-5">
                <form class="flex items-center">
                    <div class="relative w-full">
                        <div class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none">
                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 " fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path></svg>
                        </div>
                        <input type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Search" required />
                    </div>
                </form>
                <button type="submit" class="p-2.5 ml-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    <span class="p-3">Open New Ticket</span>
                </button>
            </div>
            <table class="w-full text-sm text-left text-gray-500 ">
                <thead class="text-xs text-gray-700 bg-gray-100">
                <tr>
                    <th scope="col" class="py-4 px-6 w-28">
                        Status
                    </th>
                    <th scope="col" class="py-4 px-6 w-auto">
                        Ticket
                    </th>
                    <th scope="col" class="py-4 px-6 w-40 text-center">
                        Agent Access
                    </th>
                    <th scope="col" class="py-4 px-6 w-36 text-center">
                        Last Update
                    </th>
                    <th scope="col" class="py-4 px-6 w-36 text-right">
                        Actions
                    </th>
                </tr>
                </thead>
                <tbody>
                <tr class="bg-white border-b hover:bg-gray-50 cursor-pointer">
                    <th scope="row" class="py-4 px-6 font-medium text-center whitespace-nowrap">
                        <span class="px-2 py-1 bg-gray-300 rounded-full">Closed</span>
                    </th>
                    <td class="py-4 px-6">
                        <div class="flex flex-col justify-start">
                            <span class="text-blue-800 font-semibold">#8746836</span>
                            <span class="font-bold text-gray-600 text-base">Privacy Error</span>
                            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit.</p>
                        </div>
                    </td>
                    <td class="py-4 px-6 text-center">
                        -
                    </td>
                    <td class="py-4 px-6 text-center">
                        5 mins ago
                    </td>
                    <td class="py-4 px-6 text-right">
                        <a href="#" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                    </td>
                </tr>

                </tbody>

            </table>
            <div class="py-4 text-right pr-4">
                <ul class="inline-flex -space-x-px">
                    <li>
                        <a href="#" class="py-2 px-3 ml-0 leading-tight text-gray-500 bg-white rounded-l-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">Previous</a>
                    </li>
                    <li>
                        <a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">1</a>
                    </li>
                    <li>
                        <a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">2</a>
                    </li>
                    <li>
                        <a href="#" aria-current="page" class="py-2 px-3 text-blue-600 bg-blue-50 border border-gray-300 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white">3</a>
                    </li>
                    <li>
                        <a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">4</a>
                    </li>
                    <li>
                        <a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">5</a>
                    </li>
                    <li>
                        <a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">Next</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <?php return ob_get_clean();
}
// add shortcode
add_shortcode('thrivedesk_conversation', 'conversation_page');
