wp-fix-post-meta
================

This plugin patches strings in post meta and in post GUIDs in a serialization-friendly way. Useful for changing all hard-coded references to a url or filesystem path when a WP build is migrated from one place to another, and especially useful when those strings live in PHP-serialized data, so a textual search-and-replace in the SQL dump won't work.

License
================

   Copyright 2013 Crowd Favorite, Ltd.

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
