<?php



class PollinoConfig extends ModuleConfig{

    public function __construct(){

        $this->add(array(
            array(
                'name' => 'form_action',
                'type' => 'text',
                'label' => 'Default form action',
                'value' => './',
                'description' => 'Default action url for the voting forms.',
                ),
            array(
                'name' => 'result_sorting',
                'type' => 'select',
                'label' => 'Default result sorting',
                'value' => 'sort',
                'description' => 'Default action url for the voting forms.',
                'options' => array(
                        'sort' => 'Sort as in tree',
                        'vote_desc' => 'Sort by Votes DESC',
                        'vote_asc' => 'Sort by Voted ASC',
                    )
                ),
            array(
                'name' => 'result_outertpl',
                'type' => 'text',
                'label' => 'Result list wrapper',
                'value' => '<ol class="pollino_list_results">{out}</ol>',
                ),
            array(
                'name' => 'answer_outertpl',
                'type' => 'text',
                'label' => 'Voting list wrapper',
                'value' => '<ul class="pollino_list">{out}</ul>',
                ),
            array(
                'name' => 'Prevent Multiple Votings',
                'type' => 'fieldset',
                'children' => array(
                    array(
                        'name' => 'prevent_voting_type',
                        'value' => 1,
                        'label' => 'Prevent Multiple Votings Method',
                        'description' => 'Choose one or the other method',
                        'type' => 'radios',
                        'options' => array(
                            'use_cookie' => 'Cookie',
                            'use_ip' => "by IP",
                            'use_user' => "by User (registered)",
                            ),
                        'columnWidth' => 100,
                        'optionColumns' => 5
                    ),
                    // cookies
                    array(
                        'name' => 'cookie_expires',
                        'value' => 86400,
                        'label' => 'Cookie Lifetime',
                        'description' => 'Lifetime of the cookie in seconds',
                        'notes' => '86400 = day, 3600 = hour',
                        'type' => 'integer',
                        'columnWidth' => 50,
                        'showIf' => 'prevent_voting_type=use_cookie',
                    ),
                    array(
                        'name' => 'cookie_prefix',
                        'value' => 'pillono_',
                        'label' => 'Cookie Prefix',
                        'description' => 'Prefix used for the client side cookies',
                        'type' => 'text',
                        'columnWidth' => 50,
                        'showIf' => 'prevent_voting_type=use_cookie',
                    ),
                    // IP
                    array(
                        'name' => 'ip_expires',
                        'value' => 86400,
                        'label' => 'IP Lifetime',
                        'description' => 'Seconds after the IP restriction will expire. Enter 0 to no expire.',
                        'notes' => '86400 = day, 3600 = hour, 0 = never expire',
                        'type' => 'integer',
                        'columnWidth' => 50,
                        'showIf' => 'prevent_voting_type=use_ip',
                    ),
                    array(
                        'name' => 'use_ua',
                        'value' => 0,
                        'label' => 'UserAgent',
                        'description' => 'Additionally use the User Agent string',
                        'type' => 'checkbox',
                        'columnWidth' => 50,
                        'showIf' => 'prevent_voting_type=use_ip',
                    ),
                    // user
                    array(
                        'name' => 'user_info',
                        'value' => "By using this option. The logged in user will be used to prevent multiple votings. The rest is up to you to handle where and how to render your polls.",
                        'label' => 'User restriction',
                        'type' => 'markup',
                        'showIf' => 'prevent_voting_type=use_user',
                    ),
                ),
            ),

        ));
    }

}