## WEVIDEO SERVICE API

By Chris Bartek, Jr.

This project is the backend API piece for creating WeVideo (end-user
editable) templates.


## Perform matching of templates if needed to export from wevideo again
Run this:
php match_template.php <xml_folder_path>

## having issues with uploading images on server? Make sure to run:
```shell

    sudo chmod -R 777 /var/www/html/service/public/uploads
```