<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
    <channel>
        <title>Your RSS Feed Title</title>
        <link>{{ url('/') }}</link>
        <description>Your RSS Feed Description</description>

        @foreach ($items as $item)
            <item>
                <title>{{ $item->title }}</title>
                <link>{{ $item->link }}</link>
                <description>{{ $item->description }} <img src={{ $item->image }} /> </description>
                <guid>{{ $item->media_id }}</guid>
                <pubDate>{{ $item->created_at->toRfc2822String() }}</pubDate>
                <image>{{ $item->image }}</image>
            </item>
        @endforeach
    </channel>
</rss>
