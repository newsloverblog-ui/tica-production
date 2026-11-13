# Browser QA screenshots

`npx playwright test` writes one full-page PNG per route, role and width here
(`<role>-<path>-desktop.png` at 1440 wide, `-mobile.png` at 390 wide).
CI uploads them as the **ui-screenshots** artifact of the "Browser QA" workflow.
The PNGs themselves are not committed.
