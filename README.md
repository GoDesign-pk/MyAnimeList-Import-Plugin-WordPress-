# MyAnimeList Automated Fetching Plugin WordPress

Task / Features
--------------------------
1.) MAL API modification: The API is already build into the theme, need it to fetch description of series, and when there isn't a English title when fetching for English titles it will use the Japanese English title instead of outputting a blank.

---> 1a.) MAL API + Fetching: The API of MAL will need to correspond with the fetching process automatically. For example when data is being fetch from source (gogoanime.vc) the plugin will send the title of the series to MAL API so it can fetch the data for anime series and publish that post if it matches title, released year. *Important: For series MAL API can't find title for have it use title of source (gogoanime.vc) and have it published as a draft without the episodes fetching being processed.

2.) Fetching Process: There should be a plugin setting page where I can select if I want to scrape data for just Subbed series, or Dubbed series or both *Important if selected both the dubbed series will have "(DUB)" at end of series title. An option to have scraper check back for new series and episodes in x time frame. And a fetch button to start the plugin to fetch data. Also an overview area where I can see how many series have been scraped in Subbed and Dubbed categories plus how many are yet to be scraped total for each categories.

---> 2a.) When fetching iframe for episodes make sure plugin checks the correct fields and for iframe title have it set as "Video Stream".

3.) Remove JavaScript loading iframe: When you publish an episode and you view it it take couple of seconds to load with javascript loading screen, I want it to normally display like an iframe tag should.

Summary
---------------
A WordPress plugin that will create anime series post using the data MAL API fetched, then it will create an episode post for every episode that is in the series.

Title format for episodes should look like this.

Title (anime series title *not source title) Episode (current episode number ex. 1) English (the type of episode Dubbed, Subbed)

One Piece Episode 1 English Dubbed
One Piece Episode 1 English Subbed

Also note* If a series is already in database but doesn't have the episodes published the plugin will fetch for those episodes and skip the series data pulling process. If series and episodes is already in database it will skip that data fetching also.
