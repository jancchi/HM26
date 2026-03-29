SET NAMES utf8mb4;

-- Map Slovak and typo category values to English canonical values in requests
UPDATE requests SET category = 'Employee Search' WHERE category IN ('Hladanie zamestnanca', 'Hľadanie zamestnanca', 'hladanie zamestnanca');
UPDATE requests SET category = 'Investor Search' WHERE category IN ('Hladanie investora', 'Hľadanie investora', 'hladanie investora');
UPDATE requests SET category = 'Event Speaking' WHERE category IN ('Speaking na evente', 'speaking na evente');
UPDATE requests SET category = 'Marketing Materials Sharing' WHERE category IN (
  'Zdielanie marketingovych podkladov',
  'Zdieľanie marketingových podkladov',
  'Zdielanie marketyngovych podkladov',
  'zdielanie marketingovych podkladov',
  'zdielanie marketyngovych podkladov'
);
UPDATE requests SET category = 'Sales Support' WHERE category IN ('Podpora v oblasti sales', 'podpora v oblasti sales');
UPDATE requests SET category = 'Client Search' WHERE category IN ('Hladanie klientov', 'Hľadanie klientov', 'hladanie klientov');
UPDATE requests SET category = 'Other' WHERE category IN ('Ine', 'Iné', 'ine');

-- Map Slovak and typo category values to English canonical values in worker_categories
UPDATE worker_categories SET category = 'Employee Search' WHERE category IN ('Hladanie zamestnanca', 'Hľadanie zamestnanca', 'hladanie zamestnanca');
UPDATE worker_categories SET category = 'Investor Search' WHERE category IN ('Hladanie investora', 'Hľadanie investora', 'hladanie investora');
UPDATE worker_categories SET category = 'Event Speaking' WHERE category IN ('Speaking na evente', 'speaking na evente');
UPDATE worker_categories SET category = 'Marketing Materials Sharing' WHERE category IN (
  'Zdielanie marketingovych podkladov',
  'Zdieľanie marketingových podkladov',
  'Zdielanie marketyngovych podkladov',
  'zdielanie marketingovych podkladov',
  'zdielanie marketyngovych podkladov'
);
UPDATE worker_categories SET category = 'Sales Support' WHERE category IN ('Podpora v oblasti sales', 'podpora v oblasti sales');
UPDATE worker_categories SET category = 'Client Search' WHERE category IN ('Hladanie klientov', 'Hľadanie klientov', 'hladanie klientov');
UPDATE worker_categories SET category = 'Other' WHERE category IN ('Ine', 'Iné', 'ine');

-- Optional status migration to English
UPDATE requests SET status = 'New' WHERE status = 'Nová';
UPDATE requests SET status = 'Resolved' WHERE status = 'Vyriešená';

-- Quick verification counts
SELECT category, COUNT(*) AS total_requests FROM requests GROUP BY category ORDER BY category;
SELECT category, COUNT(*) AS total_workers FROM worker_categories GROUP BY category ORDER BY category;
