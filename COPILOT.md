*Role*: Act as a Senior Software Architect. *Task*: Audit my current codebase against the *PasarKraft Project Requirements* provided below.

*--- PROJECT SPECIFICATION (SOURCE CONTEXT) ---*
*System Purpose*: A web-based digital marketplace for Malaysian woodcraft and batik.

*Core Architecture*: A three-layered system: *Presentation* (Web UI), *Application* (Business Logic & ML), and *Data* (MySQL).

*User Roles & Workflows:*
- *Buyer*: Must be able to Register/Login, Search/Filter products by color/pattern, manage a Wishlist, and initiate a *Chat with Seller* to buy products.
- *Seller (Artisan)*: Must have a Dashboard for Shop Analytics and CRUD functionality to Add/Edit/Delete product listings (including image uploads and stock management).
- *Admin*: Must be able to manage all users and export reports in CSV format.

*Hybrid Recommender System:*
- *Content-Based Filtering*: Uses *TF-IDF* on product descriptions/metadata (category, material, price) to match user interests.
- *Collaborative Filtering*: Uses *Matrix Factorization* on user interaction logs (clicks, favorites).

*Required Tech Stack*:
- *Frontend*: HTML5, CSS3, JavaScript.
- *Backend*: PHP (Server-side logic) and MySQL (Database).
- *Machine Learning*: Python using Scikit-learn, Pandas, and NumPy.

*Out of Scope*: The system *should NOT* have physical delivery tracking, mobile apps (iOS/Android), or integrated payment gateways (it uses chat-to-purchase).

*--- AUDIT INSTRUCTIONS ---*

1. Check if the *Hybrid Recommender* module is implemented correctly according to the TF-IDF and Matrix Factorization requirements.

2. Verify that the Chat Interface is present for buyers to contact sellers.

3. Identify any missing CRUD functions in the *Seller Dashboard*.

4. Ensure the tech stack matches the PHP/Python/MySQL requirement.

Please provide a list of "Completed," "Incomplete," and "Missing" features based on the codebase you see.