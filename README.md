# OpenWhen

**Every delivery stop becomes an observation of whether the shop was really open — and tomorrow's route is put in the order most likely to find each shutter up.**

Google Maps hours for a small shop were set once, by the owner, years ago. Then he started closing for lunch, or for market day, or for Friday prayer, or because he is at the bank. On a dense informal-retail route, ten to twenty per cent of stops find a shutter down. The only people who know when shop 37 on that street is actually open are the drivers who call on it weekly — and they know it in their heads, which is why a replacement driver spends weeks bouncing off shutters.

---

## The one idea

**A closed door is data.**

Route optimisers take time windows as an input and never learn them. Google's hours are owner-declared. "Popular times" measures footfall and cannot tell you the shutter was down. Field-sales apps log a visit outcome and then use it for a sales report.

Here, four taps — delivered, shutter down, owner away, refused — each with a time and a place. Per shop, per weekday, per hour, what fraction of visits found it open. After about six visits a shape appears, and the lunch closure nobody ever wrote down anywhere shows up as a stripe through the middle of an otherwise solid row.

Three thresholds keep that honest, and each of them is a constant in `app/Services/OpenWindowEstimator.php` with a test against it:

- An hour is only read once it has been visited **twice**.
- A weekday says nothing at all under **four** visits, and says so out loud.
- A rule like *"rarely open before ten on Mondays"* needs **three** observations in that stretch and near-total agreement. One unlucky Monday at nine proves nothing, and Laplace smoothing means it cannot read as certainty even arithmetically: one closed visit gives 0.33, never 0.

---

## What it refuses to do

**It does not reorder what it has not seen.** A shop with no profile stays exactly where the driver put it, and the line says so: *"not enough visits yet — left where you had it"*. A tool that shuffles somebody's whole list on its first day, on two weeks of data, is switched off by its second.

**It never joins one distributor's visits to another's.** There is no table in this schema that could. A shared cross-distributor open-hours graph is an obvious later product and an obvious later temptation, and the moment it exists what is being shared stops being opening hours and becomes "who calls on which shop, how often". That belongs to whoever paid for the diesel.

**It counts a tap once.** The driver app works with no signal: the tap is queued in the browser with an id generated *before* it is sent, and the server answers 200 for an id it has already seen. A replayed "closed" that counted twice would quietly move a shop's profile and nobody would ever notice.

---

## What it does

| | |
|---|---|
| **The driver's day** | Today's stops in order, four buttons per stop the size of half a phone. Dark by default, because this is used in a van before dawn. |
| **Offline** | Taps are kept on the phone and sent when there is signal. The header says so, so nobody has to guess. |
| **The shop's week** | A heat map of weekday against hour, every cell somebody's tap, plus the window and any rules strong enough to state. |
| **Sequencing** | Tomorrow's route ordered by when each shop is usually open — with the reason on every line. |
| **Repeat** | The same van does the same street every Tuesday. Copy the route forward; the order is the only thing that changes. |
| **Wasted stops** | Closed shutters and absent owners, counted. It is the number that justifies the subscription. |
| **Languages** | Albanian and English, per person. |

---

## Running it

Needs PHP 8.2+ and Composer. No Node, no build step: the CSS and the outbox are hand-written.

```bash
git clone https://github.com/Andis382/openwhen.git
cd openwhen
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Open http://localhost:8000 and log in as `demo@openwhen.test` / `password` (Genti, the dispatcher), or `fatmir@openwhen.test` to see what a driver sees.

Ten weeks of taps are seeded with the four behaviours this has to handle: a shop that never opens before ten on Mondays, one that shuts for two hours at lunch, one closed every Wednesday afternoon for the market, and two too new to say anything about.

**Database.** SQLite by default, which needs no setup but does need `pdo_sqlite`. For Postgres set `DB_CONNECTION=pgsql` and the usual credentials.

**Tests.**

```bash
php artisan test
```

30 tests. If your PHP has no `pdo_sqlite`: `DB_CONNECTION=pgsql DB_DATABASE=openwhen_test php artisan test`.

---

## How the estimate works

```
per shop, per weekday, per hour:
   open  = delivered + refused + owner away     (the shutter was up)
   shut  = closed
   p     = (open + 1) / (n + 2)                 Laplace, so nothing is ever certain

   an hour is read only at n >= 2
   a weekday is read only at n >= 4
   open window = first .. last hour with p >= 0.60
   best hour   = the middle of that window, not its edge
   a rule needs 3 observations and p <= 0.25 across the stretch
```

`app/Services/OpenWindowEstimator.php` is about 200 lines and every promise in it is pinned in `tests/Unit/OpenWindowEstimatorTest.php`, because a window an hour too wide sends a van to a shutter once a week and nobody ever traces it back to a threshold.

---

## Layout

```
app/
  Services/
    OpenWindowEstimator.php   when is this shop actually open
    RouteSequencer.php        tomorrow's stops, in time order, with reasons
    VisitRecorder.php         one tap, written exactly once
  Models/  Company · Shop · Route · Stop · Visit
  Http/Controllers/Concerns/InCompany.php   another distributor's row does not exist
public/js/outbox.js           ~90 lines of localStorage queue. Not a service worker.
resources/views/              Blade, mobile first, dark first
design-system/openwhen/       tokens, type scale, the rules and why
public/css/app.css            hand written, no build step
lang/{sq,en}/                 the interface, and every reason a stop gives
tests/                        30: the estimator, sequencing, idempotency, tenancy
```

---

## What it looks like, and why

Dark by default, and that is not a style choice: this is used in a van at half past five in the morning with the interior light off, held in one hand. The light theme exists for the dispatcher at a desk and is the exception. Tap targets are 56px rather than the usual 48, because a mis-tap here does not just annoy somebody — it puts a false observation into a shop's history.

The system is written down in [`design-system/openwhen/MASTER.md`](design-system/openwhen/MASTER.md). Three rules are enforced by `tests/Unit/InterfaceDisciplineTest.php`: no emoji as icons, no status that means something only by colour, and a tap-target floor with visible focus and honoured reduced motion.

---

## Honest notes

- **Nothing here is a route optimiser.** It reorders for time, not distance. A distributor with five vans knows his own town far better than any solver; what nobody knows is the hours. Bolting a TSP onto this would be the fun problem rather than the real one.
- **The evidence takes weeks.** A shop needs roughly six visits on a weekday before it says anything, which on a weekly route is a month and a half. The product is honest about that on every screen, and it is the main reason a pilot has to run a season rather than a fortnight.
- **The offline outbox is deliberately crude.** localStorage and a drain loop, not a service worker. It is ninety lines a three-year-old Android runs without asking permission for anything, and it fails visibly rather than cleverly. It does not survive the browser data being cleared, and it says so rather than pretending.
- **Anchor customers matter.** Food, dairy, pharmacy, household goods. Not alcohol or tobacco distribution.
- **No ERP integration, no stock, no invoicing.** Customers come in by hand or by CSV. Those are what make the enterprise alternatives cost what they cost.

## Roadmap

- CSV import for the customer list
- A printable sheet for the replacement driver on his first morning
- Time-of-day detail below the hour, once there is enough data to earn it
- Weather and market days as inputs, once the basic signal is proven

## Licence

MIT. See [LICENSE](LICENSE).
