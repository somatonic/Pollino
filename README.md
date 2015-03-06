# Pollino (beta)

### A simple poll module for ProcessWire

This module makes it simple to setup polls for your website. It is based on simple page setup to create polls. Each poll is a page, and its children are the answers. Pollino will create the templates and a PollinoPolls page in the root to start with. You can add fields to the templates as you wish and later use hooks to modify the output of the poll. This can be useful to for example to use images as options or just some custom markup etc.

It provides some API to render the poll form and the result. These methods are hookable and it's easy to customize the output if needed.

Pollino takes care of saving and preventing multiple votes. It comes with some configuration settings to choose what method to use to prevent from multiple votings:

- using cookie and an expire time
- or by IP and optionally also the UserAgent with and expire time
- or by logged in User

Pollino isn't 100% plug'n'play but comes with some premade theme and output for you to start.

It does support multilanguage, as all strings are translatable in the module. Also since it's using simple pages and templates you're free to add or change fields to make its output multilanguage without much hassle.

### Requirements

- ProcessWire 2.5.5
- jQuery for when using ajax enhancement

### Installation

When installing the module it will create some templates for you to setup polls.

- pollino_polls (main folder template that holds the poll pages)
- pollino_poll
- pollino_answer

Pollino will also create the a page "PollinoPolls" in the root of you site using the "pollino_polls" template.

Add the css that comes with the module ```pollino.css``` to your site.

If you want to enable ajax support for the polls, just add the javascript that comes with the module: ```pollino-ajax-script.js``` and it will enhance the poll to use ajax.

### Create and output your first poll

You can create a new poll by adding a child to the newly created /pollinopolls/ page (feel free to rename it). The title of the page would represent the question of poll.

Then add as many child pages to the poll that represent the answers. Make sure those are all published. (You can alternatively use PageTable field to make them addable and editable directly from the puestion page).

Create a new template file "pollino_poll" and add this example code to render the poll.

```
$content .= "<div class='pollino_poll'>";
$content .= "<div class='inner'>";
$content .= "<h3>$page->title</h3>";
$content .= $modules->Pollino->renderPoll($page);
$content .= "</div>";
$content .= "</div>";
```

The ```renderPoll(Pollpage)``` method will just return the form with the options, or if the user already voted, the result list.

As you can see you have to add the wrapper container ```.pollino_poll``` to hold the poll and its title of the poll. This makes it easier to customize the output. I also used a ```.inner``` div as you can see, but that's purely personal preference to keep things more flexible.

Make the css ``pollino.css``` that comes with the module your own and modify to your needs to create you own theme.

### Configurations and Options

In the module screen you have various options to configure the module.

These options can also be set directly via API using setOptions() method.

```
$pollino = $modules->Pollino;
$pollino->setOptions($options);
```

Options are:
```
$options = array(
    'form_action' => './', // overwrite action url of form
    'prevent_voting_type' => 'use_cookie', // can be either of these use_cookie|use_ip|use_user
    'cookie_expires' => 86400,
    'cookie_prefix' => 'pollino_',
    'ip_expires' => 86400,
    'use_ua' => 0, // whether to use UA string additionally when using use_ip

    'result_sorting' => 'sort', // ca be either of these sort|vote_desc|vote_asc
    'result_outertpl' => '<ol class="pollino_list_results">{out}</ol>',
    'answer_outertpl' => '<ul class="pollino_list">{out}</ul>',
);
```

You can also add one or more configurations (named) in your site/config.php and use them.

Example add this to your config

```
$config->pollino = array(
        'myconfig1' => array(
            'form_action' => './',
            'prevent_voting_type' => 'use_ip',
            'ip_expires' => 85400,
            'result_sorting' => 'sort',
            ),
    );
```

Then use the configuration name "myconfig1" in your calls.

```
$pollino->setOptions("myconfig1");
```

Or when using the render method renderPoll()

```
$pollino->renderPoll($page, "myconfig1");
```

To render a poll view only you can force that by setting the second or third argument to true:

```
echo $pollino->renderPoll($page, $viewOnly = true);
```

or

```
echo $pollino->renderPoll($page, $options, $viewOnly = true);
```

### Get results via API

You can also get the result of a poll by using getVoteResults(poll, type). It returns an array by default.

To get the results in form of the answer pages as an PageArray use "pages" keyword:
```
$answerPages = $modules->Pollino->getVoteResults($poll, "pages");
```

The returned answer pages in the PageArray contain properties added you can use to output a result list. The $poll page will contain the total votes $poll->vote_total

```
foreach($answerPages as $answer) {
    echo $answer->vote_count;
    echo $answer->vote_percent;
}
echo $poll->vote_total;
```

To get a associative array with the results for each answer leave second argument blank or "array";
```
$answerArray = $modules->Pollino->getVoteResults($poll, "array");
```

The returned array will contain count, percent and answer text

```
foreach($answerArray as $answer) {
    echo $answer['vote_count'];
    echo $answer['vote_percent'];
    echo $answer['vote_text'];
}
```

### Hooks to modify output

All of the render methods are hookable, so you can easily replace them with your own methods.

```
renderPollForm($page) // renders form with rows
renderFormRow($item, $key) // renders only the rows

renderPollResult($page) // renders the result list
renderResultRow($item, $key) // renders only the result rows
```

For example to customize the result output you can add this hook to a autoload module (HelloWorld.module)

```
$this->addHookBefore("Pollino::renderResultRow", $this, "hookPollinoResultRow");
```

And then the method to replace the results output. Here with an additional thumbnail image and in a table instead of a ul list.

```
public function hookPollinoResultRow($event) {

    $item = $event->arguments("item");
    $key = $event->arguments("key");
    $event->replace = true;

    $img = "";
    if($item->pollino_image){
        $imageUrl = $item->pollino_image->size(50,50)->url;
        $img = "<span class='pollino_image'><img src='$imageUrl'></span>";
    }

    $row = "\n<tr><td colspan='2'><span class='pollino_percent_wrapper'><span class='pollino_percent_bar stretchRight' style='width:{$item->vote_percent}%'></span></span></td></tr>
                <tr><td>{$img} {$item->title}</td><td>{$item->vote_count} ({$item->vote_percent}%)</td>
            </tr>";

    $event->return = $row;

}
```

To make this work you must also modify the "result_outertpl" to be a table. Like

```
$options = array(
    'result_outertpl' => '<ol class="pollino_list_results">{out}</ol>'
)
echo $modules->Pollino->renderPoll($page, $options);

```

You could also replace the ```renderPollResult($page)``` completely. Best would be to copy the complete method and modify it.

From there, anything is possible to create your own output and maybe even integrate some chart visualisations etc.

Have Fun.

## Support & dontations

I'm always there to help out with problems or question. It might just take a while for me to respond. Feel free to ask.

If you find a bug or feature you wish, just open an issue on https://github.com/somatonic/Pollino/issues

Making such modules takes lots of time. Feel free to support me and buy me a beer or make a donation.
My Paypal address is [philipp at urlich dot ch]

Or via flattr.com, you can simply like my repository to make a donation.


## Copyright & License

Pollino Polls for ProcessWire to help setup simple polls

Copyright (C) 2015 Philipp 'Soma' Urlich

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
