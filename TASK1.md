*Role*: Senior Full-Stack Developer and Machine Learning Engineer. 

*Task*: Complete the remaining 30% of the *PasarKraft* project based on the following technical specifications and current status report.

1. *Hybrid Recommender Implementation (Python Stack)*

- *Current Status*: Logic is currently in PHP. It must be moved to a Python-based microservice or script.

- *ML Stack*: Use *Python* with *Scikit-learn, Pandas, and NumPy*.

*Data Sources*: Fetch data from the *MySQL* database. Use product_descriptions (textual metadata) and user_interaction_logs (views, clicks, favorites).

*Logic - Content-Based Filtering*: Implement *TF-IDF Feature Extraction* on product metadata (name, category, material, and pattern). Use *Cosine Similarity* to match product attributes with user interests.

*Logic - Collaborative Filtering*: Implement *Matrix Factorization* on user interaction logs to identify latent patterns in user behavior. 

*Hybrid Engine*: Create a scoring function that combines candidates from both models to mitigate the *cold start* and *data sparsity* problems.

*Output*: The script should return a list of recommended product IDs that the PHP backend can consume and display on the homepage.php and wishlist_page.php.

2. *Server-Side Search & Filtering Logic*'

- *Current Status*: Filtering by color/pattern is currently UI-only in homepage.php.

- *Required*: Write the PHP logic to query the MySQL database using the filter parameters (Category: Batik vs. Woodcraft, Material, Pattern, Color). Ensure it handles "No products found" states gracefully.

3. *Data-Driven Seller Analytics*

- *Current Status*: dashboard_seller.php uses hard-coded values.

- *Required*: Replace static KPIs with SQL aggregations. Calculate *Estimated Revenue, Active Inquiries, Products Listed, and Average Rating* dynamically from the database.

- *Deliverable*: Please provide the Python ML pipeline script (recommender.py) and the updated PHP snippets for the search logic and seller dashboard.