{{-- resources/views/rss/gitir.blade.php --}}
<?php header('Content-Type: application/rss+xml; charset=utf-8'); ?>
<rss version="2.0">
    <channel>
        <title>Your RSS Feed Title</title>
        <link>Your Website URL</link>
        <description>Your RSS Feed Description</description>

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
