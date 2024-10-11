<html>
<head>
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin=""/>
    <link
        rel="stylesheet"
        as="style"
        onload="this.rel='stylesheet'"
        href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Space+Grotesk%3Awght%40400%3B500%3B700"
    />

    <title>Galileo Design</title>
    <link rel="icon" type="image/x-icon" href="data:image/x-icon;base64,"/>

    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
</head>
<body>
<div class="relative flex size-full min-h-screen flex-col bg-[#f8fbfb] group/design-root overflow-x-hidden"
     style='font-family: "Space Grotesk", "Noto Sans", sans-serif;'>
    <div class="flex items-center bg-[#f8fbfb] p-4 pb-2 justify-between">
        <div class="text-[#0e1b17] flex size-12 shrink-0 items-center" data-icon="ArrowLeft" data-size="24px"
             data-weight="regular">
            <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" fill="currentColor"
                 viewBox="0 0 256 256">
                <path
                    d="M224,128a8,8,0,0,1-8,8H59.31l58.35,58.34a8,8,0,0,1-11.32,11.32l-72-72a8,8,0,0,1,0-11.32l72-72a8,8,0,0,1,11.32,11.32L59.31,120H216A8,8,0,0,1,224,128Z"></path>
            </svg>
        </div>
        <h2 class="text-[#0e1b17] text-lg font-bold leading-tight tracking-[-0.015em] flex-1 text-center pr-12">
            Contributions</h2>
    </div>
    <div class="flex flex-wrap gap-4 px-4 py-6">
        <div class="flex min-w-72 flex-1 flex-col gap-2 rounded-lg border border-[#d1e6e0] p-6">
            <p class="text-[#0e1b17] text-base font-medium leading-normal">Contributions in the last year</p>
            <p class="text-[#0e1b17] tracking-light text-[32px] font-bold leading-tight truncate">{{ $totalContributions }}
                contributions</p>
            <div class="flex min-h-[180px] flex-1 flex-col gap-8 py-4">
                <h1>GitHub Contributions Calendar</h1>
                <svg id="contributions-calendar" width="960" height="500"></svg>


            </div>
        </div>
    </div>
    <h3 class="text-[#0e1b17] text-lg font-bold leading-tight tracking-[-0.015em] px-4 pb-2 pt-4">Contribution
        Summary</h3>
    <div class="flex items-center gap-4 bg-[#f8fbfb] px-4 min-h-[72px] py-2">
        <div class="flex flex-col justify-center">
            <p class="text-[#0e1b17] text-base font-medium leading-normal line-clamp-1">Most Contributions</p>
            <p class="text-[#509581] text-sm font-normal leading-normal line-clamp-2">{{ $mostContributions }}
                contributions on {{ $mostContributionsDate }}</p>
        </div>
    </div>
    <div class="flex items-center gap-4 bg-[#f8fbfb] px-4 min-h-[72px] py-2">
        <div class="flex flex-col justify-center">
            <p class="text-[#0e1b17] text-base font-medium leading-normal line-clamp-1">Longest Streak</p>
            <p class="text-[#509581] text-sm font-normal leading-normal line-clamp-2">{{ $longestStreak }} days of
                contribution</p>
        </div>
    </div>
    <div class="flex items-center gap-4 bg-[#f8fbfb] px-4 min-h-[72px] py-2">
        <div class="flex flex-col justify-center">
            <p class="text-[#0e1b17] text-base font-medium leading-normal line-clamp-1">Total Contributions</p>
            <p class="text-[#509581] text-sm font-normal leading-normal line-clamp-2">{{ $totalContributions }} total
                contributions</p>
        </div>
    </div>
    <h3 class="text-[#0e1b17] text-lg font-bold leading-tight tracking-[-0.015em] px-4 pb-2 pt-4">Contribution
        Heatmap</h3>
    <p class="text-[#0e1b17] text-base font-normal leading-normal pb-3 pt-1 px-4">The color intensity represents how
        much you've contributed that day. Darker is more.</p>
    <!-- Add your heatmap SVG or component here -->
</div>
</body>

<script>
    // Select the SVG element by ID
    const svg = d3.select('#contributions-calendar');
    const width = +svg.attr('width');  // Get the width from the SVG attribute
    const height = +svg.attr('height'); // Get the height from the SVG attribute

    // Fetch the data
    fetch('http://localhost:8000/api/calendar-data')
        .then(response => response.json())
        .then(data => {
            // Create scales
            const xScale = d3.scaleBand()
                .domain(data.map(d => d.date))
                .range([0, width])
                .padding(0.1);

            const yScale = d3.scaleLinear()
                .domain([0, d3.max(data, d => +d.count)])
                .range([height, 0]);

            // Create bars
            svg.selectAll('.bar')
                .data(data)
                .enter()
                .append('rect')
                .attr('class', 'bar')
                .attr('x', d => xScale(d.date))
                .attr('y', d => yScale(+d.count))
                .attr('width', xScale.bandwidth())
                .attr('height', d => height - yScale(+d.count));
        })
        .catch(error => {
            console.error('Error fetching data:', error);
        });

</script>

</html>
