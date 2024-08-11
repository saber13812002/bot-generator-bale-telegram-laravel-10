{{-- resources/views/rss/gitir.blade.php --}}
<?php header('Content-Type: application/rss+xml; charset=utf-8'); ?>
<rss version="2.0">
    <channel>
        <title>{{ $xml->channel->title }}</title>
        <link>{{ $xml->channel->link }}</link>
        <description>{{ $xml->channel->description }}</description>
        <atom:link href="https://git.ir/feed-fa/" rel="self">

        </atom:link>
        <language>fa-IR</language>
        <lastBuildDate>Sun, 11 Aug 2024 21:39:49 +0000</lastBuildDate>

        @foreach ($items as $item)
            <item>
                <title>{{ $item->title }}</title>
                <link>{{ $item->link }}</link>
                <description>
                    <![CDATA[
                    @if (!empty($item->imageUrl))
                        <img src="{{ $item->imageUrl }}" alt="{{ $item->title }}"/>
                    @endif
                    {{ $item->description }}
                    ]]>
                </description>
                <guid>{{ $item->link }}</guid>
                <pubDate>{{ $item->pubDate }}</pubDate>
            </item>
        @endforeach
    </channel>
</rss>
