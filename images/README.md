# images/

Every image the site uses lives in this folder. There are no images anywhere
else in the tree, and nothing is loaded from a remote host: the
Content-Security-Policy allows `img-src 'self' data:` only.

| File | Purpose |
|---|---|
| `logo.svg` | The Pioneer Public Library wordmark with its book mark. Used in the site footer. |
| `placeholder-cover.svg` | The neutral "Cover" tile that stands in for a catalogue book cover, matching the cover box in WF 09 and WF 10. |
| `favicon.svg` | The green square with the open book, declared in the `<head>` of every page. |

All three are hand-written SVG, so they stay sharp at any size, weigh a few
hundred bytes each, and need no binary assets in the repository. To swap in
real artwork later, keep the same file names and the pages pick it up with no
markup change.

Phase 1 draws the wordmark as text in the header, exactly as the wireframes
show it. `logo.svg` exists for the footer and for any later page that wants the
mark rather than the words.
