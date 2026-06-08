Role: Senior Machine Learning Engineer and PHP Developer. Task: Finalize the PasarKraft Hybrid Recommender and update the Seller Dashboard based on client feedback.

1. Complete recommender.py Logic:
Content-Based: Implement TfidfVectorizer on the metadata column (title, description, category, etc.). Use cosine_similarity to find products matching the user's past favorites.

Collaborative: Create a user-item matrix from the interaction logs and apply TruncatedSVD to predict scores for unviewed items.

Hybrid Output: Blend these scores and return the top 8 Product IDs as a JSON object for the PHP homepage.

2. Update dashboard_seller.php:
The client noted revenue analytics are incorrect since there is no payment system.
Action: Change the dashboard to display "Total Buyer Inquiries" (count of unique chat threads) and "Wishlist Adds" (count of users who favorited their products).

3. Create generate_test_data.py:
Write a script to populate the MySQL user_interaction_logs table with synthetic data for 50 users and 100 products (clicks and favorites) to allow for effective model training.

4. Homepage Integration:
Provide the PHP snippet for homepage.php to execute the Python script and render the recommended products in a "Recommended for You" section.

5. Optional Chatbot:
Include a simple JavaScript-based chatbot for the buyer homepage to help users discover Batik or Woodcraft categories.