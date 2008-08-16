<?php
/*
 * Modified / fixed from the original by Robin Adrianse (a.k.a. rob1n) http://robinadr.com/
 * http://robinadr.com/projects/footnotes
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class Footnotes extends Plugin
{
	const VERSION = '2.2';
	private $footnotes;
	private $current_id;

	const FLICKR_KEY= '22595035de2c10ab4903b2c2633a2ba4';

	public function info()
	{
		return array(
			'name' => 'Footnotes',
			'url' => 'http://habariproject.org',
			'author' => 'Habari Community',
			'authorurl' => 'http://habariproject.org',
			'version' => self::VERSION,
			'description' => 'Use footnotes in your posts. Syntax: &lt;footnote&gt;Your footnote&lt;/footnote&gt;, wherever you want the reference point to be. Everything is done automatically. You can also cite a source using a url attribute like this &lt;footnote url="http://foo.bar/foo/"&gt;Title&lt;/footnote&gt;.',
			'license' => 'Apache License 2.0'
		);
	}

	public function filter_plugin_config( $actions, $plugin_id )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			$actions[] = _t('Configure');
		}
		return $actions;
	}

	public function action_plugin_ui( $plugin_id, $action )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			switch ( $action ) {
			case _t('Configure') :
				$form = new FormUI( strtolower( get_class( $this ) ) );
				$form->append( 'static', 'why_suppress', _t('<small>If you suppress the list, you can add them manually using the $post->footnotes array.</small>') );
				$form->append( 'checkbox', 'suppress_list', 'footnotes__suppress_list', _t('Don\'t append the footnote list to posts') );
				$form->append( 'submit', 'save', _t('Save') );
				$form->out();
				break;
			}
		}
	}

	public function filter_post_content( $content, $post )
	{

		// If we're on the publish page, replacement will be destructive.
		// We don't want that, so return here.
		$controller = Controller::get_handler();
		if ( $controller->action == 'admin' && isset($controller->handler_vars['page']) && $controller->handler_vars['page'] == 'publish' ) {
			return $content;
		}

		// If there are no footnotes, save the trouble and just return it as is.
		if ( strpos( $content, '<footnote' ) === false ) {
			return $content;
		}

		$this->footnotes = array();
		$this->current_id = $post->id;

		$return = preg_replace_callback( '/<footnote(\s+url=[\'"].*[\'"])?>(.*)<\/footnote>/Us', array('self', 'add_footnote'), $content );

		if ( count( $this->footnotes ) == 0 ) {
			return $content;
		}

		$post->footnotes= $this->footnotes;
		
		$append = '';
		
		if ( !Options::get('footnotes__suppress_list') ) {

			$append.= '<ol class="footnotes">' . "\n";

			foreach ( $this->footnotes as $i => $footnote ) {
				// if there was a url
				if ( is_array($footnote) ) {

					switch ( $footnote['type'] ) {
					case "flickr":
						$append .= '<li id="footnote-' . $this->current_id . '-' . $i . '" class="cite '. $footnote['type'] . '">';
						$append .= '<a href="' . $footnote['photo']['url'] . '" title="' . $footnote['photo']['title'] . '">Photo</a>';
						$append .= ' by <a href="' . $footnote['owner']['url'] . '" title="' . $footnote['owner']['name'] . '">' . $footnote['owner']['username'] . '</a>';
						$append .= ' on <a href="http://flickr.com">Flickr</a>';
						$append .= ' <a href="#footnote-link-' . $this->current_id . '-' . $i . '">&#8617;</a>';
						$append .= "</li>\n";
						break;

					case "vanilla":
						$append .= '<li id="footnote-' . $this->current_id . '-' . $i . '">';
						$append .= '<a href="' . $footnote['url'] . '" title="' . $footnote['text'] . '">' . $footnote['text'] . '</a>';
						$append .= ' <a href="#footnote-link-' . $this->current_id . '-' . $i . '">&#8617;</a>';
						$append .= "</li>\n";
						break;

					}

				}
				else {

					$append .= '<li id="footnote-' . $this->current_id . '-' . $i . '">';
					$append .=  $footnote;
					$append .= ' <a href="#footnote-link-' . $this->current_id . '-' . $i . '">&#8617;</a>';
					$append .= "</li>\n";
				}
			}

			$append .= "</ol>\n";

		}

		return $return . $append;
	}

	private function add_footnote( $matches )
	{
		$url = $matches[1];
		$footnote = $matches[2];
		if ( $url != '' ) {
			$url = preg_replace('/\s+url=[\'"](.*)[\'"]/', '$1', $url);
			$url_parts = array();

			$info = array();

			// It's a link to a flickr photo
			if ( preg_match('/http:\/\/flickr.com\/photos\/(\w*)\/(\d*)/', $url, $url_parts) ) {
				$user = $url_parts[1];
				$photo = $url_parts[2];

				if ( Cache::has('footcite__' . $photo) ) {
					$fetch = Cache::get('footcite__' . $photo);
				}
				else {
					$fetch = RemoteRequest::get_contents('http://api.flickr.com/services/rest/?method=flickr.photos.getInfo&api_key='.self::FLICKR_KEY.'&photo_id='.$photo);
					Cache::set('footcite__' . $photo, $fetch);
				}

				$xml = new SimpleXMLElement($fetch);

				$info['type'] = 'flickr';
				$info['photo'] = array(
					'title' => (string) $xml->photo->title,
					'id' => $photo,
					'url' => (string) $xml->photo->urls->url[0]
				);

				$info['owner'] = array(
					'username' => (string) $xml->photo->owner['username'],
					'name' => (string) $xml->photo->owner['realname'],
					'id' => (string) $xml->photo->owner['nsid'],
					'url' => 'http://www.flickr.com/photos/' . $user . '/'
				);

			}
			else {
				$info['type'] = 'vanilla';
				$info['url'] = $url;
				$info['text'] = $footnote;
			}

			$footnote = $info;
		}

		$i = count( $this->footnotes ) + 1;

		$this->footnotes[$i] = $footnote;
		$id = $this->current_id . '-' . $i;

		return '<sup class="footnote-link" id="footnote-link-' . $id . '"><a href="#footnote-' . $id . '" rel="footnote">' . $i . '</a></sup>';
	}

	public function action_update_check () {
		Update::add( 'Footnotes', '021e0510-a3cc-4a9b-9faa-193596f04dcb', self::VERSION );
	}

}

?>
