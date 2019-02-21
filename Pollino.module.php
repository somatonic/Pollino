<?php
/**
 * Pollino
 * ProcessWire Poll Module
 *
 * 2015 by Soma Philipp Urlich
 *
 * ProcessWire 2.x
 * Copyright (C) 2010 by Ryan Cramer
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 *
 * http://www.processwire.com
 * http://www.ryancramer.com
 *
 */


/**
 * Form output
<form action="./" method="get" class="pollino_form">
    <input type="hidden" name="pollino_poll" value="1020">
    <input type="hidden" name="pollino_config" value="myconfig">
    <ul class="pollino_list">
        <li class="pollino_item"><label><input type="radio" name="pollino_answer" value="1021"> New York</label></li>
        <li class="pollino_item"><label><input type="radio" name="pollino_answer" value="1022"> London</label></li>
        <li class="pollino_item"><label><input type="radio" name="pollino_answer" value="1023"> Paris</label></li>
    </ul>
    <input class='pollino_submit' type="submit" value="Abstimmen">
</form>
*/

/**
 * Result output
<ol class="pollino_list_results"><li class="pollino_item pollino_item0">
        New York (66.7%, 2)
        <span class="pollino_percent_wrapper">
            <span class="pollino_percent_bar" style="display: block; width:66.7%;">&nbsp;</span>
        </span>
    </li><li class="pollino_item pollino_item1">
        London (33.3%, 1)
        <span class="pollino_percent_wrapper">
            <span class="pollino_percent_bar" style="display: block; width:33.3%;">&nbsp;</span>
        </span>
    </li><li class="pollino_item pollino_item2">
        Paris (0%, 0)
        <span class="pollino_percent_wrapper">
            <span class="pollino_percent_bar" style="display: block; width:0%;">&nbsp;</span>
        </span>
    </li>
</ol>
 */


class Pollino extends WireData implements Module {


    const TABLE_PREFIX = "pollino_";

    const COOKIE_PREFIX = "pollino_";

    public function __construct() {
        parent::__construct();
        $this->set("config_name", "");
        if($this->wire('config')->ajax) {
            $this->addHookBefore('Page::render', $this, 'checkVoteActionAjax');
        }
    }


    public function init() {}


    public function setOptions($options = array()){
        if(is_string($options)){
            $configName = $options;
            if( $this->wire("config")->pollino && isset($this->wire("config")->pollino[$configName]) ) {
                $this->setOptions($this->wire("config")->pollino[$configName]);
                $this->config_name = $configName;
            }
        }
        if(is_array($options) && count($options)) {
            $this->data = array_merge($this->data, $options);
        }

    }

    public function ___renderPoll(Page $poll = null, $options = null, $view_only = false) {

        if(is_bool($options)){
            $view_only = $options;
        } else if(is_array($options) || is_string($options)) {
            $this->setOptions($options);
        }

        // print_r($this->data);

        if(!$poll || !$poll->id) $poll = $this->wire("page");

        $result = $this->checkVoteAction($poll);
        $alreadyVoted = $this->hasVoted($poll);

        if($alreadyVoted || $result['success'] || $view_only){
            $out = "";
            $message = $result['message'];
            if($message) $out .= "<p class='pollino_error'>$message</p>";
            $out .= $this->renderPollResult($poll);
            return $out;
        } else {
            $out = "";
            $message = $result['message'];
            if($message) $out .= "<p class='pollino_error'>$message</p>";
            $out .= $this->renderPollForm($poll);
            return $out;
        }

    }

    public function ___renderPollForm(Page $poll) {

        $out = "";
        $list = "";
        $answers = $poll->children("template=pollino_poll_answer");

        if(!$answers->count) return __("Poll not configured: Missing answers.");

        foreach($answers as $key => $answer){
            $list .= $this->renderFormRow($answer, $key);
        }

        $list = str_replace("{out}", $list, $this->answer_outertpl);
        $button = "<input class='pollino_submit' type='submit' value='". $this->_("Vote") . "'>";

        $configField = "";
        if($this->config_name) {
            $configField = "<input type='hidden' name='pollino_config' value='{$this->config_name}'>";
        }

        $loader = "<span class='pollino_loader pollino_hidden'></span>";

        $out .= "<form action='{$this->form_action}' method='get' class='pollino_form'>
                    <input type='hidden' name='pollino_poll' value='{$poll->id}'>
                    {$configField}
                    {$list}
                    {$button} {$loader}
                </form>";

        return $out;
    }

    public function ___renderFormRow($item, $key) {
        $row = "\n<li class='pollino_item'>
                    <label><input type='radio' name='pollino_answer' value='{$item->id}'> $item->title</label>
                </li>";
        return $row;
    }

    public function ___renderPollResult(Page $poll) {

        $answers = $poll->children("template=pollino_poll_answer");
        if(!$answers->count) return __("Poll not configured: Missing answers.");

        $list = "";

        $answers = $this->getVoteResults($poll, "pages");

        if($this->result_sorting == "sort") $sort = "sort";
        if($this->result_sorting == "vote_desc") $sort = "-vote_count";
        if($this->result_sorting == "vote_asc") $sort = "vote_count";

        foreach($answers->sort($sort) as $key => $aw){
            $list .= $this->renderResultRow($aw, $key);
        }

        // $chart = "<div class='pollino_chart'><canvas id='pollino_chart_{$poll->id}' width='100px' height='100px'></canvas></div>";
        $list = str_replace("{out}", $list, $this->result_outertpl);
        $total = "<p class='pollino_total'>" . $this->_("Total votings:") . " $poll->vote_total</p>";
        $out = $list . $total;

        return $out;
    }


    public function ___renderResultRow($item, $key) {
        $row = "\n<li class='pollino_item pollino_item$key'>
                <span class='pollino_percent_wrapper'>
                    <span class='pollino_percent_bar stretchRight' style='width:{$item->vote_percent}%;'>&nbsp;</span>
                </span>
                {$item->title} ({$item->vote_percent}%, {$item->vote_count})
            </li>";

        return $row;
    }


    public function ___getVoteResults(Page $poll, $type = "array")  {

        if(!$type) throw new WireException("Empty return type specified as the second argument in getVoteResults().");
        if(!in_array($type, array("array", "pages"))){
            throw new WireException("Sorry, The '$type' = not allowed reuturn type keyword");
        }

        $answers = $poll->children("template=pollino_poll_answer");

        $database = $this->wire('database');
        $table = self::TABLE_PREFIX . "votes";
        $sql = "SELECT COUNT(*) as count, vote_id as answer_id FROM `$table` WHERE page_id=:page_id GROUP BY vote_id ORDER BY count DESC";
        $query = $database->prepare($sql);
        $query->bindValue(':page_id', $poll->id, PDO::PARAM_INT);

        $result = false;

        try {
            $query->execute();
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            $error = $e->getMessage();
            $this->error($e->getMessage());
        }

        $data = array();
        $total = 0;

        // vote_id = answerthe voting Page id in ProcessWire
        if(count($result)) {
            foreach($result as $res) {
                $data[$res["answer_id"]]['vote_count'] = $res['count'];
                $total += $res['count'];
                $poll->vote_total = $total;
            }
            foreach($answers as $answer) {
                $answer->vote_percent = isset($data[$answer->id]) ? number_format(round((100/$total) * $data[$answer->id]['vote_count'], 1), 1, ".", " ") : 0;
                $answer->vote_count = isset($data[$answer->id]) ? $data[$answer->id]['vote_count'] : 0;
                $data[$answer->id]['vote_text'] = $answer->title;
                $data[$answer->id]['vote_count'] = $answer->vote_count;
                $data[$answer->id]['vote_percent'] = $answer->vote_percent;
            }
        } else {
            foreach($answers as $answer) {
                $answer->vote_count = 0;
                $answer->vote_percent = 0;
                $data[$answer->id]['vote_text'] = $answer->title;
                $data[$answer->id]['vote_count'] = $answer->vote_count;
                $data[$answer->id]['vote_percent'] = $answer->vote_percent;
            }
        }

        if($type == "array") return $data;
        if($type == "pages") return $answers;
        return "";
    }


    public function ___addVote(Page $poll, Page $answer) {

        $database = $this->wire('database');
        $table = self::TABLE_PREFIX . "votes";
        $sql = "INSERT INTO `$table` SET page_id=:page_id, vote_id=:vote_id, ip=:ip, agent=:agent, user_id=:user_id";
        $query = $database->prepare($sql);
        $query->bindValue(':page_id', $poll->id, PDO::PARAM_INT);
        $query->bindValue(':vote_id', $answer->id, PDO::PARAM_INT);
        $query->bindValue(':ip', $this->wire('session')->getIP(), PDO::PARAM_STR);
        $query->bindValue(':agent', trim($_SERVER['HTTP_USER_AGENT']), PDO::PARAM_STR);
        $query->bindValue(':user_id', $this->wire('user')->id, PDO::PARAM_INT);

        $result = false;

        try {
            $result = $query->execute();
            if($result) $this->setHasVoted($poll);
        } catch(Exception $e) {
            // duplicate or fail
            $error = $e->getMessage();
            if($this->wire('config')->debug && !$this->wire('user')->isLoggedin()) {
                $this->error($e->getMessage());
            } else {
                $this->error($this->_('Error recording vote'));
            }
        }

        return $result;

    }

    public function checkVoteAction($page) {

        $input = $this->wire("input");
        $pages = $this->wire("pages");

        // if($input->get->pollino_poll == $page->id && $input->get->pollino_answer) {
        if($input->get->pollino_poll == $page->id && $input->get->pollino_answer) {

            $vote_id = (int) $input->get->pollino_poll;
            $answer_id = (int) $input->get->pollino_answer;
            $answer_page = $pages->get($answer_id);
            $vote_page = $pages->get($vote_id);

            if(!$vote_page->id) return false;
            if(!$answer_page->id) return false;

            // already voted?
            if($this->hasVoted($vote_page)){
                $result = array(
                    'success' => 0,
                    'message' => __("You already have voted."),
                );
                return $result;
            }

            $success = $this->addVote($vote_page, $answer_page);
            $message = $success ? "" : $this->errors("clear string");

            $result = array(
                'success' => $success,
                'message' => $message,
                );

            if($success){
                $this->wire("session")->redirect($this->wire("page")->url . "?pollino_voted={$vote_id}");
            } else {
                return $result;
            }

        } else if($input->get->pollino_poll == $page->id) {

            $result = array(
                'success' => false,
                'message' => __("Please choose an answer first."),
                );

            return $result;
        }

        return false; // no action
    }


    public function checkVoteActionAjax($page) {

        $input = $this->wire("input");
        $pages = $this->wire("pages");

        if($input->get->pollino_config) {
            $configName = $input->get->pollino_config;
            if( $this->wire("config")->pollino && isset($this->wire("config")->pollino[$configName]) ) {
                $this->setOptions($this->wire("config")->pollino[$configName]);
                // $this->wire("log")->save("pollino", "ajax config ok");
            }
        }

        // if($input->get->pollino_poll == $page->id && $input->get->pollino_answer) {
        if($input->get->pollino_poll && $input->get->pollino_answer) {

            $vote_id = (int) $input->get->pollino_poll;
            $answer_id = (int) $input->get->pollino_answer;
            $answer_page = $pages->get($answer_id);
            $vote_page = $pages->get($vote_id);

            if(!$vote_page->id) return false;
            if(!$answer_page->id) return false;

            // already voted?
            if($this->hasVoted($vote_page)){
                $result = array(
                    'success' => 0,
                    'message' => __("You already have voted."),
                );
                header("Content-type: application/json");
                echo json_encode($result);
                exit;
            }

            $success = $this->addVote($vote_page, $answer_page);
            $message = $success ? "" : $this->errors("clear string");

            if($success) {
                $result = $this->renderPollResult($vote_page);
                echo $result;
                exit;
            } else {
                header("Content-type: application/json");
                $result = array(
                    "success" => 0,
                    "message" => $message,
                    );
                echo json_encode($result);
                exit;
            }

        } else if($input->get->pollino_poll) {
            header("Content-type: application/json");
            $result = array(
                "success" => 0,
                "message" => __("Please select an answer first."),
                );
            echo json_encode($result);
            exit;
        }

    }

    public function setHasVoted(Page $poll) {

        if($this->prevent_voting_type == "use_cookie"){
            $this->setCookie($poll);
        }

    }

    public function hasVoted(Page $poll, $user = null) {

        if($this->prevent_voting_type == "use_cookie"){
            return $this->hasCookie($poll);
        }

        if($this->prevent_voting_type == "use_ip" && !$this->use_ua){
            return $this->checkByIP($poll);
        } else if($this->prevent_voting_type == "use_ip" && $this->use_ua){
            return $this->checkByIPAndAgent($poll);
        }

        if($this->prevent_voting_type == "use_user"){
            return $this->checkByUser($poll, $user);
        }

        return false;

    }

    public function checkByIP(Page $poll) {
        if($this->ip_expires > 0) $lifetime = time() - $this->ip_expires;
            else $lifetime = 0;
        $database = $this->wire('database');
        $table = self::TABLE_PREFIX . "votes";
        $sql = "SELECT * FROM `{$table}` WHERE page_id=:page_id AND ip=:ip AND created>=:created";
        $query = $database->prepare($sql);
        $query->bindValue(':page_id', $poll->id, PDO::PARAM_INT);
        $query->bindValue(':ip', $this->wire('session')->getIP(), PDO::PARAM_STR);
        $query->bindValue(':created', date("Y-m-d H:i:s", $lifetime), PDO::PARAM_STR);
        if(!$query->execute()) return false;
        if(!$query->rowCount()) return false;
        return true;
    }

    public function checkByIPAndAgent(Page $poll) {
        if($this->ip_expires) $lifetime = time() - $this->ip_expires;
            else $lifetime = 0;
        $database = $this->wire('database');
        $table = self::TABLE_PREFIX . "votes";
        $sql = "SELECT * FROM `{$table}` WHERE page_id=:page_id AND agent=:agent AND ip=:ip AND created>=:created";
        $query = $database->prepare($sql);
        $query->bindValue(':page_id', $poll->id, PDO::PARAM_INT);
        $query->bindValue(':ip', $this->wire('session')->getIP(), PDO::PARAM_STR);
        $query->bindValue(':agent', trim($_SERVER['HTTP_USER_AGENT']), PDO::PARAM_STR);
        $query->bindValue(':created', date("Y-m-d H:i:s", $lifetime), PDO::PARAM_STR);
        if(!$query->execute()) return false;
        if(!$query->rowCount()) return false;
        return true;
    }

    public function checkByUser(Page $poll, $user) {
        $userid = $user && $user->id ? $user->id : $this->wire("user");
        $database = $this->wire('database');
        $table = self::TABLE_PREFIX . "votes";
        $sql = "SELECT * FROM `{$table}` WHERE page_id=:page_id AND user_id=:user_id";
        $query = $database->prepare($sql);
        $query->bindValue(':page_id', $poll->id, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userid, PDO::PARAM_INT);
        if(!$query->execute()) return false;
        if(!$query->rowCount()) return false;
        return true;
    }

    public function hasCookie($vote_id) {
        if(isset($_COOKIE[self::COOKIE_PREFIX . $vote_id])) return true;
            else return false;
    }

    public function setCookie($vote_id) {
        // $this->wire("log")->save("pollino", $this->cookie_expires);
        setcookie(self::COOKIE_PREFIX . $vote_id, '1', time() + $this->cookie_expires, '/');
    }


    public function ___install() {

        if(!$this->wire->templates->get("name=pollino_polls")->id){
            $fg_folder = new Fieldgroup();
            $fg_folder->name = "pollino_polls";
            $fg_folder->add($this->wire->fields->get("title"));
            $fg_folder->save();

            $tpl_folder = new Template();
            $tpl_folder->name = "pollino_polls";
            $tpl_folder->label = "Polls";
            $tpl_folder->set("fieldgroup", $fg_folder);
            $tpl_folder->save();
        }

        if(!$this->wire->templates->get("name=pollino_poll")->id){
            $fg_poll = new Fieldgroup();
            $fg_poll->name = "pollino_poll";
            $fg_poll->add($this->wire->fields->get("title"));
            $fg_poll->save();

            $tpl_folder = $this->wire->templates->get("name=pollino_polls");
            $tpl_poll = new Template();
            $tpl_poll->name = "pollino_poll";
            $tpl_poll->label = "Poll";
            $tpl_poll->set("fieldgroup", $fg_poll);
            $tpl_poll->parentTemplates = array($tpl_folder->id);
            $tpl_poll->noShortcut = 0;
            $tpl_poll->save();

            $tpl_folder->childTemplates = array($tpl_poll->id);
            $tpl_folder->save();
        }

        if(!$this->wire->templates->get("name=pollino_poll_answer")->id){
            $fg_answer = new Fieldgroup();
            $fg_answer->name = "pollino_poll_answer";
            $fg_answer->add($this->wire->fields->get("title"));
            $fg_answer->save();

            $tpl_poll = $this->wire->templates->get("name=pollino_poll");
            $tpl_answer = new Template();
            $tpl_answer->name = "pollino_poll_answer";
            $tpl_answer->label = "Poll Answer";
            $tpl_answer->set("fieldgroup", $fg_answer);
            $tpl_answer->parentTemplates = array($tpl_poll->id);
            $tpl_answer->save();

            $tpl_poll->childTemplates = array($tpl_answer->id);
            $tpl_poll->save();

        }



        $folder = $this->wire->pages->get("template=pollino_polls, has_parent!={$this->wire->config->trashPageID}");
        if(!$folder->id){
            $folder = new Page();
            $folder->template = "pollino_polls";
            $folder->parent = "/";
            $folder->title = "PollinoPolls";
            $folder->save();
            $this->message("Added 'PollinoPolls' folder page to root");
        }

        $tablename = self::TABLE_PREFIX . "votes";
        $sql = "
            CREATE TABLE IF NOT EXISTS `{$tablename}` (
                `id` int auto_increment NOT NULL,
                `page_id` int unsigned NOT NULL,
                `vote_id` int unsigned NOT NULL,
                `created` TIMESTAMP NOT NULL,
                `ip` VARCHAR(15) NOT NULL default '',
                `agent` VARCHAR(255) NOT NULL default '',
                `user_id` int unsigned NOT NULL default 0,
                PRIMARY KEY (`id`),
                INDEX `created` (`created`)
            ) ENGINE={$this->wire->config->dbEngine} DEFAULT CHARSET={$this->wire->config->dbCharset}";

        $this->database->exec($sql);


    }

    public function ___uninstall() {

        $tablename = self::TABLE_PREFIX . "votes";
        $sql = "DROP TABLE IF EXISTS `{$tablename}`";
        $this->database->exec($sql);

    }

}