# Security Notes

## Reporting

Report issues privately to the address configured in `SUPPORT_EMAIL`
(see `.env.example`) instead of opening a public issue.

## Accepted risks

### Public text-to-speech endpoint

`POST /news/{id}/generate-audio` stays available to anonymous readers because
the audio button is part of the public article page. Exposure is bounded by:

- `throttle:3,1` on the route;
- one generation at a time per article (`Cache::lock`);
- existing audio is returned instead of being generated again;
- text is capped (60 chunks) and responses are size- and MP3-signature-checked;
- failures are logged.

Requiring login would remove the feature for readers; queueing it would need a
production queue worker and a polling UI. Both remain options if abuse is seen.

### Advertisement HTML

Administrators with the `ads_manage` permission can store raw HTML/JavaScript,
which is printed unescaped by design so ad networks work. Only trusted
administrators should receive that permission.
