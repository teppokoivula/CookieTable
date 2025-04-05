Cookie Table module for ProcessWire CMS/CMF
-------------------------------------------

Cookie Table is a module for ProcessWire CMS/CMF intended for adding a cookie table on your site, i.e. a list of cookies that your site uses along with key details about each cookie: name, provider (if known), description, duration, and so on.

When installed, Cookie Table adds a "Cookies" page into the admin interface, by default under "Setup". Cookies page provides a simple interface for managing — adding, updating, or removing — listed cookies. On your site you can use the render method to render a prebuilt table of known cookies:

```php
echo $modules->get('CookieTable')->render();
```

Alternatively you can get a list of cookies as a raw array and render the list any way you prefer:

```php
$cookies = $modules->get('CookieTable')->getCookies();
foreach ($cookies as $cookie) {
	echo "Cookie name: " . $cookie['name'] . "<br>";
}
```
