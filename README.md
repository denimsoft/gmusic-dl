Requires [gmusicapi](https://github.com/simon-weber/gmusicapi): `pip install gmusicapi`

### Bookmarklets

#### Get onscreen album/user playlist id:
    javascript:alert(decodeURIComponent(document.querySelector('.cover-card').attributes['data-id'].value.replace(/\/.+/,%20'')))
#### Get playing track id:
    javascript:alert(document.querySelector('.currently-playing').attributes['data-id'].value)
