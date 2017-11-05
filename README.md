# <img src="https://raw.githubusercontent.com/InsanityRadio/OnAirController/master/doc/headphones_dark.png" align="left" height=48 /> Showplan

Showplan is a WordPress plugin for managing your radio station's schedule.

This is not a mature product. The current release (0.1) is considered an alpha, and we can't guarantee it'll work without issues.

## Installation

The current beta can be installed by <a href="https://github.com/InsanityRadio/Showplan/archive/master.zip">downloading the tarball</a> of the project, and either uploading it to WordPress or extracting it in the plugins directory.

Showplan needs to run on PHP 5.6 (might work on 5.5, but untested). Older PHP versions are no longer supported, so it's probably worth updating your web server if you are running anything less. 

Once you've activated the plugin, click "Terms" in the Showplan menu, and create a new one. Showplan is designed for stations (like Insanity) who's schedule completely changes every few months. If this is not what you want, enter start and end dates (format YYYY-MM-DD works best)  like 1970-01-01 to 2100-01-01.

You can now edit the Schedule Template to set up your regular schedule. You can make weekly changes through Overrides. 

Changes to the calendars won't show up on the website until you click Publish - this allows you to stage multiple changes at once without making them public and breaking your live schedule. Changes to show information (titles, descriptions) will update immediately, however. 

Add some shortcode to your schedule page. See API below for a full list.

## Roadmap

### Now

* Basic scheduling based on calendar "terms"
* Multiple station support out of the box
* Automatic sustainer filling 
* API call to get upcoming shows and guide
* <b>Changing timezone support</b>
* Schedule widget for website frontend: `[showplan-schedule]`
* "On Air" widget (or equiv) for website frontend: `[showplan-now-title]` etc.

### Up Next

* Fix multiple station support
* Select term to edit
* Categories
* TimeZone change (is currently set to the TimeZone that WordPress uses)
* Unit and E2E testing 

### Later

* Terms of more than one week (ie. week A/B)
* Show Images and/or Pages
* More API calls

## API

TBC. However as a proof of concept, you can currently visit the following URI to dump the next three shows:

`http://localhost:8080/wp-content/plugins/showplan/api.php?method=get_upcoming&station_id=1`

The database is designed to allow non-complicated reading from anything capable of connecting to a MySQL server. See the `$prefix_compiled_times` table for more (start_time and end_time are always UTC, start_time_local is based on the WordPress site's timezone)

In WordPress you can access various data using short codes. Your theme needs to support shortcodes in widgets in order to use them in widgets.

`[showplan-now-title station=1]` Current show title

`[showplan-now-description station=1]` Current show description

`[showplan-now-hosts station=1]` Current show hosts

`[showplan-now-start station=1]` Current show start time (HH:II)

`[showplan-now-end station=1]` Current show end time (HH:II)

`[showplan-next-... station=1]` Same as above, but for the next show

`[showplan-schedule station=1 images=0 sustainer=1 days=7]` Render a full calendar widget on the frontend
