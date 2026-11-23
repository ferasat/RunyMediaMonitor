<?php

namespace App\Infrastructure\Services;

class RssParser
{
    /** @return array<int, array<string, mixed>> */
    public function parse(string $xml): array
    {
        $items = [];
        try {
            $doc = @new \SimpleXMLElement($xml);
            // RSS 2.0
            if (isset($doc->channel->item)) {
                foreach ($doc->channel->item as $item) {
                    $items[] = [
                        'title' => (string) $item->title,
                        'link' => (string) $item->link,
                        'description' => (string) $item->description,
                        'pubDate' => (string) $item->pubDate,
                        'guid' => (string) ($item->guid ?: $item->link),
                    ];
                }
            }
            // Atom
            if (isset($doc->entry)) {
                foreach ($doc->entry as $entry) {
                    $link = '';
                    foreach ($entry->link as $l) {
                        if ((string) $l['rel'] === 'alternate' || (string) $l['rel'] === '') {
                            $link = (string) $l['href'];
                            break;
                        }
                    }
                    $items[] = [
                        'title' => (string) $entry->title,
                        'link' => $link,
                        'description' => (string) ($entry->summary ?: $entry->content),
                        'pubDate' => (string) ($entry->published ?: $entry->updated),
                        'guid' => (string) ($entry->id ?: $link),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // ignore parse errors
        }
        return $items;
    }
}