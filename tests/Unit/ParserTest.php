<?php
/**
 * Parser tests: readme.txt, Markdown, JSON and detection.
 *
 * @package Releaso
 */

namespace Releaso\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Releaso\Parser\JsonParser;
use Releaso\Parser\MarkdownParser;
use Releaso\Parser\ParseException;
use Releaso\Parser\ParserFactory;
use Releaso\Parser\ReadmeParser;

/**
 * @covers \Releaso\Parser\ReadmeParser
 * @covers \Releaso\Parser\MarkdownParser
 * @covers \Releaso\Parser\JsonParser
 * @covers \Releaso\Parser\ParserFactory
 * @covers \Releaso\Parser\AbstractLineParser
 */
class ParserTest extends TestCase {

	/**
	 * A fixture.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	private function fixture( $name ) {
		return (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/' . $name );
	}

	public function test_readme() {
		$releases = ( new ReadmeParser() )->parse( $this->fixture( 'readme.txt' ) )->all();

		$this->assertSame( array( '2.1.0', '2.0.0', '1.9' ), array_map( fn( $r ) => $r->version, $releases ) );
		$this->assertSame( array( '2026-09-29', '2026-08-12', '' ), array_map( fn( $r ) => $r->date, $releases ) );

		$first = $releases[0]->changes;
		$this->assertCount( 4, $first );
		$this->assertSame( 'Panorama height on mobile when the toolbar is open', $first[1]->text, 'wrapped line joins its item' );
		$this->assertSame( 'Pro', $first[1]->tag );
		$this->assertSame( array( 'new', 'fixed', 'security', 'fixed' ), array_map( fn( $c ) => $c->type, $first ) );

		$this->assertSame( array( 'improved', 'new' ), array_map( fn( $c ) => $c->type, $releases[1]->changes ), 'bold section heading, explicit prefix wins' );
		$this->assertSame( 'Initial release', $releases[2]->changes[0]->text, 'plain lines are items in a readme' );
	}

	public function test_readme_stops_at_next_section() {
		$releases = ( new ReadmeParser() )->parse( $this->fixture( 'readme.txt' ) );
		$this->assertNull( $releases->find( 'Security release.' ) );
		$this->assertCount( 3, $releases );
	}

	public function test_markdown() {
		$releases = ( new MarkdownParser() )->parse( $this->fixture( 'CHANGELOG.md' ) )->all();

		$this->assertSame( array( '3.0.0', '2.4.0' ), array_map( fn( $r ) => $r->version, $releases ), 'Unreleased is skipped' );
		$this->assertSame( '2026-10-01', $releases[0]->date );
		$this->assertSame( 'The **big** one.', $releases[0]->notes );
		$this->assertSame( 'Summer release', $releases[1]->title );
		$this->assertSame( 'removed', $releases[1]->changes[0]->type );

		$carousel = $releases[0]->changes[1];
		$this->assertSame( array( 'new', 'blocks' ), array( $carousel->type, $carousel->tag ) );
		$this->assertSame( 'fixed', $releases[0]->changes[2]->type );
	}

	public function test_markdown_unreleased_can_be_kept() {
		$releases = ( new MarkdownParser( null, true ) )->parse( $this->fixture( 'CHANGELOG.md' ) );
		$this->assertSame( 'Unreleased', $releases->all()[0]->version );
	}

	public function test_markdown_body_fill() {
		$release = new \Releaso\Model\Release( '1.0.0' );
		( new MarkdownParser() )->fill( $release, "Intro line.\n\n## What's Changed\n* Fix crash\n* feat: dark mode" );
		$this->assertSame( 'Intro line.', $release->notes );
		$this->assertSame( array( 'fixed', 'new' ), array_map( fn( $c ) => $c->type, $release->changes ) );
	}

	public function test_json() {
		$releases = ( new JsonParser() )->parse( $this->fixture( 'changelog.json' ) )->all();

		$this->assertCount( 2, $releases, 'a version without digits is skipped' );
		$this->assertSame( array( '1.2.0', 'Hello' ), array( $releases[0]->version, $releases[0]->title ) );
		$this->assertSame( array( 'new', 'fixed' ), array_map( fn( $c ) => $c->type, $releases[0]->changes ) );
		$this->assertSame( 'Pro', $releases[0]->changes[1]->tag );
		$this->assertSame( '2026-09-01', $releases[1]->date );
		$this->assertSame( array( 'fixed', 'security' ), array_map( fn( $c ) => $c->type, $releases[1]->changes ) );
	}

	public function test_json_github_releases_shape() {
		$json     = json_encode(
			array(
				array(
					'tag_name'     => 'v2.0.0',
					'name'         => 'Big one',
					'published_at' => '2026-09-01T10:00:00Z',
					'body'         => "### Bug fixes\n- Crash",
				),
				array(
					'tag_name' => 'v2.1.0-beta',
					'draft'    => true,
				),
			)
		);
		$releases = ( new JsonParser() )->parse( $json )->all();
		$this->assertCount( 1, $releases );
		$this->assertSame( array( '2.0.0', 'Big one', '2026-09-01', 'fixed' ), array( $releases[0]->version, $releases[0]->title, $releases[0]->date, $releases[0]->changes[0]->type ) );
	}

	public function test_json_invalid() {
		$this->expectException( ParseException::class );
		( new JsonParser() )->parse( '{ nope' );
	}

	public function test_detect() {
		$this->assertSame( 'readme', ParserFactory::detect( $this->fixture( 'readme.txt' ) ) );
		$this->assertSame( 'markdown', ParserFactory::detect( $this->fixture( 'CHANGELOG.md' ) ) );
		$this->assertSame( 'json', ParserFactory::detect( $this->fixture( 'changelog.json' ) ) );
		$this->assertSame( 'markdown', ParserFactory::detect( '- a line', 'https://x.test/CHANGELOG.md?raw=1' ) );
		$this->assertSame( 'readme', ParserFactory::detect( "\xEF\xBB\xBF= 1.0 =\r\n* x" ), 'BOM and CRLF' );
	}

	public function test_bold_component_heading_is_not_an_item() {
		$release = ( new ReadmeParser() )->parse( "= 11.1.2 2026-09-22 =\n\n**WooCommerce**\n\n* Fix - Variation gallery\n* Add - Blocks\n\nNote:\n* Plain" )->latest();
		$this->assertSame( '2026-09-22', $release->date );
		$this->assertSame( array( 'Variation gallery', 'Blocks', 'Plain' ), array_map( fn( $c ) => $c->text, $release->changes ) );
		$this->assertSame( array( 'fixed', 'new' ), array( $release->changes[0]->type, $release->changes[1]->type ) );
	}

	public function test_dates() {
		$parser = new ReadmeParser();
		$rel    = $parser->parse( "= 1.0 - 29.09.2026 =\n* a\n= 0.9 (3rd March 2026) =\n* b\n= 0.8 - nonsense =\n* c" )->all();
		$this->assertSame( array( '2026-09-29', '2026-03-03', '' ), array_map( fn( $r ) => $r->date, $rel ) );
		$this->assertSame( 'nonsense', $rel[2]->title );
	}
}
