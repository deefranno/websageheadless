# Managing your website

Your website's content lives in WordPress. You change it there, and the public website updates by itself, usually within a few seconds. You never need to touch code.

Your developer will give you the WordPress address (it often ends in `/wp-admin`), a username and a password.

## The basics

- **Pages** are your main pages: Home, About, Services, Contact and so on.
- **Posts** are blog articles.
- **Sections** are the building blocks of a page, such as a big welcome banner, a block of text with a picture, or a list of questions and answers.
- **WebSage Settings** holds things that appear on every page: your logo, colours, contact details and footer.

Nothing you do in WordPress appears on the public website until you click **Publish** (for something new) or **Update** (for something already live).

## Edit a page

1. In the left menu, click **Pages** and then the page you want.
2. Click a section to change its text. Settings for the selected section (for example its colour theme or button link) are in the panel on the right; click the ⚙ icon at the top right if it is hidden.
3. Click **Update**.

**Add a section:** click the **+** button where you want it, search for the section name, and click it.

| Section              | Use it for                                                                                        |
| -------------------- | ------------------------------------------------------------------------------------------------- |
| Hero                 | The large banner at the top of a page, with a heading and up to two buttons                       |
| Text and image       | A paragraph or two next to a picture                                                              |
| Feature grid         | Several short points side by side (add a **Feature** inside it for each point)                    |
| Call to action       | A short message with one button, such as "Book a call"                                            |
| Saved call to action | Reuse a call to action you made under **Calls to action** (change it once, it updates everywhere) |
| Testimonials         | Quotes from your customers (pick them from **Testimonials**)                                      |
| FAQ                  | Questions and answers visitors can open (add a **Question** for each one)                         |
| Contact form         | The contact form, with your own heading and thank-you message                                     |

You can also use normal paragraphs, headings, lists and images between sections.

**Move a section:** select it and use the up and down arrows in its toolbar. **Remove it:** select it, click the ⋮ menu in its toolbar, then **Delete**.

**Colour theme:** most sections offer _Default_, _Muted_ or _Brand_ under **Colour theme** in the right-hand panel. Alternating them helps visitors see where one section ends and the next begins.

Each page should start with one Hero (it holds the page's main heading).

## Preview before publishing

Click **Preview** (top right) to see your changes on the real website before anyone else can. A banner at the top reminds you that you are previewing. Only you can see a preview; the link stops working after a few minutes.

## Write a blog post

1. Click **Posts → Add New Post**.
2. Add a title and your text.
3. In the right-hand panel: choose **Categories**, and set a **Featured image** (shown in the blog list and when the post is shared).
4. Click **Publish**.

## Search and sharing

Below the editor of each page and post is a box called **Search & sharing**:

- **Search title**: the title shown in Google and in the browser tab. Leave it empty to use the page title.
- **Search description**: one or two sentences shown under the title in search results.
- **Sharing image**: the picture shown when someone shares the page on social media.
- **Hide from search engines**: tick only for pages that should not appear in Google.

## Menus

Go to **Appearance → Menus**. The site has two menus: **Main navigation** (top of every page) and **Footer navigation**. Add pages from the left, drag to reorder, drag an item slightly to the right to make it a drop-down under the item above, then click **Save Menu**.

## Logo, colours and site details

Go to **WebSage Settings** in the left menu. Tabs at the top:

- **Branding:** site name, logo, fonts and brand colours. If you pick colours that make text hard to read, WordPress keeps your previous colours and tells you why.
- **Header:** an announcement bar across the top of the site (leave empty to hide it).
- **Footer:** footer text, copyright line and social media links.
- **Contact:** your public email, phone and address, who receives form messages, and how long messages are kept.
- **SEO:** how page titles are built, your organisation's name, and the default sharing image.
- **Integration:** technical settings for your developer.

Click **Save settings**. The whole site updates within a few seconds.

## Testimonials and calls to action

- **Testimonials:** add each quote as its own item (the quote, plus who said it, their role, company and an optional photo). Then pick them in a Testimonials section.
- **Calls to action:** create a call to action once and place it on many pages with the **Saved call to action** section.

## Contact form messages

Every message sent through the website's form is saved under **Form messages** (only administrators can see them) and emailed to the address set in **WebSage Settings → Contact**. Messages are deleted automatically after the number of days set there. If the email could not be sent, WordPress keeps the message and tries again.

## If something looks wrong

- **My change is not showing:** check you clicked **Update** or **Publish**, then reload the website page. If it still does not show after a minute, tell your developer; **WebSage Settings → Diagnostics** will help them.
- **Please don't** install other plugins, change the theme or change the settings under **Integration** without asking your developer. The website does not use WordPress themes, so changing one has no effect on it.
