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
	private $footnotes;
	private $current_id;
	private $post;

	const FLICKR_KEY = '22595035de2c10ab4903b2c2633a2ba4';

	public function configure()
	{
		$form = new FormUI( strtolower( get_class( $this ) ) );
		$form->append( 'static', 'why_suppress', _t('<small>If you suppress the list, you can add them manually using the $post->footnotes array.</small>') );
		$form->append( 'checkbox', 'suppress_list', 'footnotes__suppress_list', _t('Don\'t append the footnote list to posts') );
		$form->append( 'submit', 'save', _t('Save') );
		return $form;
	}

	public function filter_post_title_out($title, $post) {
		$this->filter_post_content_out($post->content, $post);
		return $title;
	}

	public function filter_post_content_out( $content, $post )
	{

		// If we're on the publish page, replacement will be destructive.
		// We don't want that, so return here.
		$controller = Controller::get_handler();
		if ( isset( $controller->action ) && $controller->action == 'admin' && isset($controller->handler_vars['page']) && $controller->handler_vars['page'] == 'publish' ) {
			return $content;
		}

		// If there are no footnotes, save the trouble and just return it as is.
		if ( strpos( $content, '<footnote' ) === false && strpos( $content, ' ((' ) === false ) {
			return $content;
		}

		$this->footnotes = array();
		$this->current_id = $post->id;

		$this->post = $post;
		$return = preg_replace_callback( '/(?:<footnote(\s+url=[\'"].*[\'"])?>|\s\(\()(.*)(?:\)\)|<\/footnote>)/Us', array($this, 'add_footnote'), $content );

		$post->footnotes = $this->footnotes;

		if ( count( $this->footnotes ) == 0 ) {
			return $content;
		}

		$post->footnotes = $this->footnotes;

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
					$append .= ' <a href="' . $this->post->permalink . '#footnote-link-' . $this->current_id . '-' . $i . '">&#8617;</a>';
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
			if ( preg_match('/http:\/\/(www\.)?flickr.com\/photos\/(.*)\/(.*)/', $url, $url_parts)) {
				$user = $url_parts[1];
				$photo = $url_parts[2];
								
				if(count($url_parts) > 3) {
					// Matched longer version
					$user = $url_parts[2];
					$photo = $url_parts[3];
				}

				if ( Cache::has('footcite__' . $photo)) {
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

		return '<sup class="footnote-link" id="footnote-link-' . $id . '"><a href="' . $this->post->permalink . '#footnote-' . $id . '" rel="footnote">' . $i . '</a></sup>';
	}
	
	public function filter_post_content_atom( $content, $post )
	{
		return $this->filter_post_content_out( $content, $post );
	}
}

?>
