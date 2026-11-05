# Countdown

A lightweight, embeddable countdown timer written in PHP. It shows the days, hours, minutes and seconds until a given date and time, and is meant to be embedded in other pages via an `<iframe>`.

## Usage

```
index.php?day=25&month=12&year=2026&hour=18&minute=30
```

### Parameters

| Parameter | Required | Default            | Description                                  |
|-----------|----------|--------------------|----------------------------------------------|
| `day`     | yes      | –                  | Day of the month (1–31)                      |
| `month`   | yes      | –                  | Month (1–12)                                 |
| `year`    | no       | next occurrence    | Year (1970 or later)                         |
| `hour`    | no       | `10`               | Hour (0–23)                                  |
| `minute`  | no       | `0`                | Minute (0–59)                                |
| `tz`      | no       | `Europe/Amsterdam` | Timezone of the target time (e.g. `UTC`, `America/New_York`) |
| `hold`    | no       | `24`               | Hours to keep showing zeros after the target has passed (0–8760, only without `year`) |

### Recurring dates

When `year` is omitted, the countdown targets the next occurrence of the date, so `?day=2&month=3` counts down to 2 March of next year once this year's date has passed. To avoid jumping straight to ~365 days the moment the countdown ends, it keeps showing zeros for `hold` hours (default 24) before moving on to next year. A 29 February target without a year counts down to the next leap year.

When `year` is given, that exact date is used and the countdown stays at zero once it has passed.

The legacy Dutch parameter names (`dag`, `maand`, `jaar`, `uur`, `minuut`) are still accepted, so existing embed URLs keep working.

The target time is interpreted in the given timezone and converted to a fixed moment, so every visitor counts down to the same instant regardless of the timezone of their own device.

### Message after the countdown ends

| Parameter            | Default | Description |
|----------------------|---------|-------------|
| `message` / `done`   | –       | Text shown instead of the boxes once the countdown has ended (max. 100 characters, URL-encoded) |

Without `message` the countdown simply shows zeros. The message uses the same colors as the numbers (`boxbg` and `text`) and also appears on a page that is already open when the time runs out.

```
index.php?day=2&month=3&message=Happy%20birthday!
```

### Colors

Colors are given as hex values **without** `#` (3, 4, 6 or 8 digits), or as `transparent`.

| Parameter                      | Default     | Description           |
|--------------------------------|-------------|-----------------------|
| `bg` / `background`            | transparent | Page background       |
| `boxbg` / `boxbackground`      | `000000dd`  | Box background        |
| `text` / `textcolor` / `numbercolor` | `eeeeee` | Number color       |
| `label` / `labelcolor`         | `ffffff99`  | Label color           |

Example:

```
index.php?day=1&month=1&year=2027&hour=0&boxbg=1e293b&text=ffffff&label=94a3b8
```

### Embedding

```html
<iframe
    src="https://example.com/countdown/index.php?day=25&month=12&hour=18"
    style="width: 100%; max-width: 720px; height: 120px; border: 0;"
    title="Countdown">
</iframe>
```

## Deployment

On every push to `main` the GitHub Actions workflow in `.github/workflows/deploy.yml` uploads the site via FTPS. It uses the `production` environment with these secrets:

- `FTP_SERVER`
- `FTP_USERNAME`
- `FTP_PASSWORD`
- `FTP_REMOTE_DIR`

## License

This project is licensed under the MIT License – see [LICENSE](LICENSE).
