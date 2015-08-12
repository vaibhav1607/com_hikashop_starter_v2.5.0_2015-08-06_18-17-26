<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.5.0
 * @author	hikashop.com
 * @copyright	(C) 2010-2015 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php
class hikashopApivoteHelper {


	public function processRequest(&$helper, $url, $params, $data) {
		switch($url) {
			case '/vote/:type/:id':
				if($params['params']['type'] != 'useful')
					return $this->setVote($helper, $url, $params, $data);
				else
					return $this->setVoteUseful($helper, $url, $params, $data);
				break;
			case '/votes/:type/:id':
				return $this->getVotes($helper, $url, $params, $data);
		}
		return false;
	}

	protected function setVote(&$helper, $url, $params, $data) {
		if(!isset($params['params']['id']) || (int)$params['params']['id'] == 0)
			return array('error' => 4001);

		if($params['params']['type'] == 'useful')
			return $this->setVoteUseful($helper, $url, $params, $data);

		$config = hikashop_config();
		$voteClass = hikashop_get('class.vote');

		$user = hikashop_loadUser(true);
		if($config->get('access_vote','public') != 'public' && is_null($user))
			return array('error' => 4002);

		if($params['params']['type'] == 'product' && $config->get('access_vote','public') == 'buyed'){
			$hasBought = $voteClass->hasBought($params['params']['id'], $user->user_id);
			if(!$hasBought)
				return array('error' => 4003);
		}

		$safeHtmlFilter = JFilterInput::getInstance(null, null, 1, 1);
		$email = trim($safeHtmlFilter->clean(strip_tags((isset($data['vote']['user_email']))?$data['vote']['user_email']:''), 'string'));
		if(is_null($user) && $config->get('email_comment','0') && empty($email))
			return  array('error' => 4004);


		$allowedVoteType = $config->get('enable_status_vote','nothing');
		$rating = (isset($data['vote']['vote_value']))?$data['vote']['vote_value']:0;
		$comment = trim($safeHtmlFilter->clean(strip_tags((isset($data['vote']['vote_comment']))?$data['vote']['vote_comment']:''), 'string'));
		$correctRating = 1;
		if((int)$rating == 0 || (int)$rating > $config->get('vote_star_number',5))
			$correctRating = 0;

		if($allowedVoteType == 'nothing')
			return array('error' => 4005);

		if($allowedVoteType == 'vote' && !$correctRating)
			return array('error' => 4006);

		if($allowedVoteType == 'comment' && $comment == '')
			return array('error' => 4007);

		if($allowedVoteType == 'two' && $comment == '' && !$correctRating)
			return array('error' => 4008);

		if($allowedVoteType == 'both' && ($comment == '' || !$correctRating))
			return array('error' => 4009);

		$nbComment = $voteClass->commentPassed($params['params']['type'], $params['params']['id'], $user->user_id);
		if(in_array($allowedVoteType,array('comment','two','both')) && !empty($comment) && $nbComment >= $config->get('comment_by_person_by_product','30'))
			return array('error' => 4010);

		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onBeforeSetVote', array( &$votes, &$params, &$data ) );

		$voteClass = hikashop_get('class.vote');
		$vote = new stdClass();


		$vote->vote_ref_id = (int)$params['params']['id'];
		$vote->vote_type = $params['params']['type'];

		$vote->vote_ip = hikashop_getIP();
		$vote->vote_date = time();
		if(!empty($vote->vote_comment) && !$config->get('published_comment','1'))
			$vote->vote_published = 0;
		else
			$vote->vote_published = 1;

		if(!is_null($user)){
			$vote->vote_user_id = $user->user_id;
			$vote->vote_pseudo = $user->username;
			$vote->vote_email = $user->email;
		}else{
			$vote->vote_user_id = hikashop_getIP();
			$vote->vote_pseudo = strip_tags((isset($data['vote']['vote_username']))?$data['vote']['username']:0);

			if(!$vote->vote_pseudo)
				return array('error' => 4011);

			if($config->get('email_comment','1')){
				$vote->vote_email = strip_tags((isset($data['vote']['vote_email']))?$data['vote']['email']:0);
				if(!$vote->vote_email)
					return array('error' => 4012);
			}
		}

		$vote->vote_rating = $rating;
		$vote->vote_comment = $comment;

		$status = $voteClass->save($vote, true);
		if(!$status)
			return array('error' => 4013);

		if($status && $config->get('email_each_comment','') != ''){
			$voteClass->sendNotifComment($status, $vote->vote_comment,$vote->vote_ref_id,$vote->vote_user_id, $vote->vote_pseudo, $vote->vote_email, $vote->vote_type);
			$mailClass = hikashop_get('class.mail');
			if(!$mailClass->mail_success)
				return array('error' => 4014);
		}

		$dispatcher->trigger('onAfterSetVote', array( &$votes, &$params, &$data ) );

		return $status;
	}

	protected function setVoteUseful(&$helper, $url, $params, $data) {

		$config = hikashop_config();

		if(!$config->get('useful_rating','0'))
			return array('error' => 4101);

		$user = hikashop_loadUser(true);
		if($config->get('register_note_comment','0') && is_null($user))
			return array('error' => 4102);

		$rating = (int)(isset($data['vote']['vote_value']))?$data['vote']['vote_value']:0;
		if(!$rating)
			return array('error' => 4103);

		$vote_id = (int)$params['params']['id'];
		$vote_user_id = hikashop_getIP();
		if(!is_null($user))
			$vote_user_id = $user->user_id;

		$already_vote = 0;
		$db = JFactory::getDBO();
		$query = 'SELECT vote_user_useful FROM '.hikashop_table('vote_user').' WHERE vote_user_id = '.(int)$vote_id.' AND vote_user_user_id = '.$db->quote($vote_user_id).'';
		$db->setQuery($query);
		$already_vote = $db->loadResult();
		if($already_vote > 0)
			return array('error' => 4104);

		$voteClass = hikashop_get('class.vote');
		$results = $voteClass->get((int)$vote_id);
		$useful = $results->vote_useful;

		if($rating == 1) {
			 $useful = ($useful + 1);
		} else {
			$useful = ($useful - 1);
		}

		$vote = new stdClass();
		$vote->vote_id = (int)$vote_id;
		$vote->vote_useful = (int)$useful;

		$query = 'UPDATE '.hikashop_table('vote').' SET vote_useful = '.(int)$useful.' WHERE vote_id = '.(int)$vote_id;
		$db->setQuery($query);
		$status = $db->query();

		if($status) {
			$dispatcher = JDispatcher::getInstance();
			$dispatcher->trigger('onAfterVoteUpdate', array( &$element, $useful ) );

			$db->setQuery('INSERT INTO '.hikashop_table('vote_user').' (vote_user_id,vote_user_user_id,vote_user_useful) VALUES ('.(int)$vote_id.','.$db->quote($vote_user_id).',1)');
			$db->query();
		}
		return $status;
	}

	protected function getVotes(&$helper, $url, $params, $data) {
		$votes = array();

		if(!isset($params['params']['id']) || (int)$params['params']['id'] == 0)
			return $votes;

		$config = hikashop_config();
		if($config->get('show_listing_comment') && is_null(hikashop_loadUser()))
			return array('error' => 4201);

		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onBeforeGetVotes', array( &$votes, &$params, &$data ) );

		$start = 0;
		$limit = $config->get('number_comment_product','50');
		if(isset($params['params']['pagination'])) {
			$start = ((int)$params['params']['pagination']['start']);
			$limit = ((int)$params['params']['pagination']['limit']);
			if($limit <= 0)
				$limit = $config->get('number_comment_product','50');
		}

		$voteClass = hikashop_get('class.vote');
		$voteClass->paginationStart = $start;
		$voteClass->paginationLimit = $limit;
		$votes = $voteClass->getList($params['params']['id'],$params['params']['type']);

		foreach($votes as $k => $vote){
			foreach($data['filters'] as $name => $value){
				$entry = 'vote_'.$name;

				if($name == 'type' ){
					if(($value == 'vote' && $vote->vote_rating == 0) || ($value == 'comment' && empty($vote->vote_comment)))
						unset($votes[$k]);
				}elseif($value != $vote->$entry){
					unset($votes[$k]);
				}
			}
		}


		$dispatcher->trigger('onAfterGetVotes', array( &$votes, &$params, &$data ) );

		return $votes;
	}
}
