# <img src="https://raw.githubusercontent.com/InsanityRadio/OnAirController/master/doc/headphones_dark.png" align="left" height=48 /> Showplan

Showplan is a WordPress plugin for managing your radio station's schedule.

It is in very early stages of development, but appears to work relatively stably. 

## Installation

The current beta can be installed by <a href="https://github.com/InsanityRadio/Showplan/archive/master.zip">downloading the tarball</a> of the project, and either uploading it to WordPress or extracting it in the plugins directory.

## Roadmap

### Now

* Basic scheduling based on calendar "terms"
* Multiple station support out of the box
* Automatic sustainer filling 
* API call to get upcoming shows and guide
* <b>Changing timezone support</b>

### Up Next

* Calender widget (or equiv) for website frontend
* "On Air" widget (or equiv) for website frontend
* TimeZone change (is currently set to the TimeZone that WordPress uses)
* Unit and E2E testing 

### Later

* Terms of more than one week (ie. week A/B)
* More API calls

## API

TBC. However as a proof of concept, you can currently visit the following URI to dump the next three shows:

`http://localhost:8080/wp-content/plugins/showplan/api.php?method=get_upcoming&station_id=1`

The database is designed to allow non-complicated reading from anything capable of connecting to a MySQL server. See the `$prefix_compiled_times` table for more (start_time and end_time are always UTC, start_time_local is based on the WordPress site's timezone)
