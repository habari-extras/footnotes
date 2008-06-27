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
	const VERSION = '1.1';
	private $footnotes;
	private $current_id;

	public function info()
	{
		return array(
			'name' => 'Footnotes',
			'url' => 'http://habariproject.org',
			'author' => 'Habari Community',
			'authorurl' => 'http://habariproject.org',
			'version' => self::VERSION,
			'description' => 'Use footnotes in your posts. Syntax: &lt;footnote&gt;Your footnote&lt;/footnote&gt;, wherever you want the reference point to be. Everything is done automatically.',
			'license' => 'Apache License 2.0'
		);
	}

	public function filter_post_content( $content, $post )
	{
		// If there are no footnotes, save the trouble and just return it as is.
		if ( strpos( $content, '<footnote>' ) === false ) {
			return $content;
		}

		$this->footnotes = array();
		$this->current_id = $post->id;

		$return = preg_replace( '/(<footnote>)(.*)(<\/footnote>)/Use', '$this->add_footnote(\'\2\')', $content );

		if ( count( $this->footnotes ) == 0 ) {
			return $content;
		}

		$append = '<ol class="footnotes">' . "\n";

		foreach ( $this->footnotes as $i => $footnote ) {
			$append .= '<li id="footnote-' . $this->current_id . '-' . $i . '">';
			$append .=  $footnote;
			$append .= ' <a href="#footnote-link-' . $this->current_id . '-' . $i . '">&#8617;</a>';
			$append .= "</li>\n";
		}

		$append .= "</ol>\n";

		return $return . $append;
	}

	private function add_footnote( $footnote )
	{
		$i = count( $this->footnotes ) + 1;

		$this->footnotes[$i] = $footnote;
		$id = $this->current_id . '-' . $i;

		return '<sup class="footnote-link" id="footnote-link-' . $id . '"><a href="#footnote-' . $id . '" rel="footnote">' . $i . '</a></sup>';
	}

	public function action_update_check ( ) {
		Update::add( 'Footnotes', '021e0510-a3cc-4a9b-9faa-193596f04dcb', self::VERSION );
	}

}

?>
