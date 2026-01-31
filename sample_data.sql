-- Sample Incident Data for Safer.ng
-- Run this in phpMyAdmin or MySQL command line after creating the database

INSERT INTO incidents (type, category, state, lga, latitude, longitude, status, start_time, end_time, victims, casualties, description) VALUES
('Kidnapping', 'Violent Crime', 'Kaduna', 'Kaduna North', 10.5167, 7.4333, 'open', '2024-01-15 14:30:00', NULL, 5, 0, 'Armed men kidnapped 5 passengers on Kaduna-Abuja highway. Security forces are in pursuit.'),
('Robbery', 'Property Crime', 'Lagos', 'Ikeja', 6.5244, 3.3792, 'closed', '2024-01-14 20:15:00', '2024-01-14 20:45:00', 2, 0, 'Armed robbery at shopping mall. Suspects apprehended by police.'),
('Terror', 'Terrorism', 'Borno', 'Maiduguri', 11.8333, 13.1500, 'open', '2024-01-16 08:00:00', NULL, 12, 3, 'Terrorist attack on village. Military response ongoing.'),
('Communal Clash', 'Conflict', 'Plateau', 'Jos North', 9.9167, 8.9000, 'pending', '2024-01-13 16:20:00', NULL, 8, 2, 'Communal violence outbreak. Situation being monitored.'),
('Road Attack', 'Violent Crime', 'Katsina', 'Katsina', 12.9886, 7.6000, 'open', '2024-01-15 10:00:00', NULL, 3, 1, 'Bandits attack on highway. Travelers advised to avoid route.'),
('Kidnapping', 'Violent Crime', 'Zamfara', 'Gusau', 12.1700, 6.6600, 'closed', '2024-01-12 18:00:00', '2024-01-13 10:00:00', 7, 0, 'School children kidnapped. All rescued by security forces.'),
('Assault', 'Violent Crime', 'Rivers', 'Port Harcourt', 4.8156, 7.0498, 'open', '2024-01-16 12:30:00', NULL, 1, 0, 'Physical assault reported in residential area.'),
('Robbery', 'Property Crime', 'Abuja', 'Abuja Municipal', 9.0765, 7.3986, 'closed', '2024-01-11 22:00:00', '2024-01-11 22:30:00', 4, 0, 'Bank robbery attempt foiled. Suspects in custody.'),
('Road Attack', 'Violent Crime', 'Sokoto', 'Sokoto North', 13.0627, 5.2439, 'open', '2024-01-14 06:00:00', NULL, 6, 2, 'Early morning attack on commuters. Security alert issued.'),
('Kidnapping', 'Violent Crime', 'Kano', 'Kano Municipal', 12.0022, 8.5919, 'pending', '2024-01-15 16:45:00', NULL, 3, 0, 'Businessman kidnapped. Ransom demand received. Investigation ongoing.'),
('Terror', 'Terrorism', 'Yobe', 'Damaturu', 11.7464, 11.9608, 'open', '2024-01-16 09:15:00', NULL, 15, 5, 'Multiple explosions reported. Emergency services responding.'),
('Communal Clash', 'Conflict', 'Benue', 'Makurdi', 7.7333, 8.5333, 'closed', '2024-01-10 14:00:00', '2024-01-10 18:00:00', 10, 3, 'Land dispute escalated. Peace restored by security forces.'),
('Robbery', 'Property Crime', 'Ogun', 'Abeokuta North', 7.1557, 3.3451, 'open', '2024-01-16 11:00:00', NULL, 2, 0, 'Armed robbery at petrol station. Suspects fled scene.'),
('Road Attack', 'Violent Crime', 'Niger', 'Minna', 9.6139, 6.5569, 'open', '2024-01-15 07:30:00', NULL, 4, 1, 'Highway banditry. Travel advisory issued.'),
('Kidnapping', 'Violent Crime', 'Kaduna', 'Chikun', 10.5167, 7.4333, 'closed', '2024-01-13 19:00:00', '2024-01-14 08:00:00', 8, 0, 'Family kidnapped from home. All rescued safely.'),
('Assault', 'Violent Crime', 'Lagos', 'Lagos Island', 6.4541, 3.3947, 'closed', '2024-01-12 15:00:00', '2024-01-12 15:30:00', 1, 0, 'Domestic violence incident. Perpetrator arrested.'),
('Terror', 'Terrorism', 'Adamawa', 'Yola North', 9.2035, 12.4954, 'open', '2024-01-16 13:00:00', NULL, 9, 2, 'Suspected terrorist activity. Security forces on high alert.'),
('Robbery', 'Property Crime', 'Enugu', 'Enugu North', 6.4528, 7.5103, 'pending', '2024-01-14 21:00:00', NULL, 3, 0, 'Home invasion robbery. Investigation in progress.'),
('Road Attack', 'Violent Crime', 'Kebbi', 'Birnin Kebbi', 12.4539, 4.1975, 'open', '2024-01-15 05:00:00', NULL, 5, 1, 'Early morning highway attack. Multiple vehicles affected.'),
('Kidnapping', 'Violent Crime', 'Taraba', 'Jalingo', 8.9000, 11.3667, 'closed', '2024-01-11 10:00:00', '2024-01-12 14:00:00', 6, 0, 'Students kidnapped. All released after negotiation.');

-- Update closed incidents with closed_at timestamp
UPDATE incidents SET closed_at = end_time WHERE status = 'closed' AND closed_at IS NULL;

