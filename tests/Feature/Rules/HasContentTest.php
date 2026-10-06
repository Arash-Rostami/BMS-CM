<?php

namespace Tests\Feature\Rules;

use App\Rules\HasContent;
use Tests\TestCase;

class HasContentTest extends TestCase
{
    private function failures(mixed $value, string $error = 'the expected error'): array
    {
        $messages = [];

        (new HasContent($error))->validate('content', $value, function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        return $messages;
    }

    public function test_a_plain_non_blank_string_passes(): void
    {
        $this->assertSame([], $this->failures('a real description'));
    }

    public function test_blank_and_whitespace_only_strings_fail(): void
    {
        $this->assertNotEmpty($this->failures(''), 'A value with no text at all must be rejected.');
        $this->assertNotEmpty($this->failures('   '), 'A value holding only whitespace must be rejected.');
    }

    public function test_an_html_tags_only_string_fails(): void
    {
        $this->assertNotEmpty($this->failures('<p><b></b></p>'), 'Markup with no text inside must be rejected.');
        $this->assertNotEmpty($this->failures('<div><br></div>'), 'A value whose tags all strip away to nothing must be rejected.');
    }

    public function test_a_value_that_is_only_an_img_or_iframe_tag_passes(): void
    {
        $this->assertSame([], $this->failures('<img src="photo.png">'));
        $this->assertSame([], $this->failures('<iframe src="video.html"></iframe>'));
    }

    public function test_an_array_node_with_non_blank_text_passes(): void
    {
        $this->assertSame([], $this->failures(['text' => 'hello']));
    }

    public function test_an_array_node_finds_content_nested_deep_in_the_tree(): void
    {
        $value = ['content' => [['content' => [['text' => 'x']]]]];

        $this->assertSame([], $this->failures($value), 'Content nested several content-levels deep must still count as content.');
    }

    public function test_an_array_node_with_blank_text_and_empty_content_fails(): void
    {
        $value = ['text' => '   ', 'content' => []];

        $this->assertNotEmpty($this->failures($value), 'A node whose text is blank and whose nested content is empty must be rejected.');
    }

    public function test_the_failure_message_is_exactly_the_constructor_error(): void
    {
        $this->assertSame(['the expected error'], $this->failures('   ', 'the expected error'));
    }
}