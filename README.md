A simple backend API that classifies a given name by gender using the Genderize API, processes the response, and returns a structured result.

🚀 Features
Accepts a name via query parameter
Integrates with external API (Genderize)
Processes and formats response data
Computes confidence level
Handles edge cases and errors properly
Returns standardized JSON responses
CORS enabled for cross-origin requests

This API accepts a user’s name, fetches data from multiple external services, processes and aggregates the results, stores them in a database, and returns a structured response.

It also supports idempotency, meaning duplicate names will not create new records.

The system uses a rule-based parser to convert plain English queries into structured filters.

Supported Keywords & Mapping
Keyword	Mapping
male / female	gender
child / teenager / adult / senior	age_group
young	min_age=16, max_age=24
above X	min_age=X
country names	mapped to ISO codes
