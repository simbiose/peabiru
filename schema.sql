DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'digest_enum') THEN
    CREATE TYPE digest_enum AS ENUM ('realtime', 'hourly', 'daily', 'weekly');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'strategy_enum') THEN
    CREATE TYPE strategy_enum AS ENUM ('osm', 'facebook', 'github', 'google', 'twitter');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'report_enum') THEN
    CREATE TYPE report_enum AS ENUM ('path_changed', 'place_changed', 'sub2place', 'sub2path', 'mention', 'isolated');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'place_enum') THEN
    CREATE TYPE place_enum AS ENUM ('city', 'farm', 'hamlet', 'isolated_dwelling', 'suburb', 'town', 'village');
  END IF;
END$$;

CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TABLE IF NOT EXISTS users (
  id SERIAL NOT NULL PRIMARY KEY,
  nick VARCHAR(20) NOT NULL,
  slug VARCHAR(30) NOT NULL,
  email VARCHAR(50),
  enabled BOOLEAN NOT NULL DEFAULT TRUE,
  digest digest_enum DEFAULT 'daily',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS users_nick ON users (nick);
CREATE UNIQUE INDEX IF NOT EXISTS users_slug ON users (slug);
CREATE UNIQUE INDEX IF NOT EXISTS users_email ON users (email);

DROP TRIGGER IF EXISTS users_update_timestamp ON users;
CREATE TRIGGER users_update_timestamp BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE PROCEDURE update_timestamp();

CREATE TABLE IF NOT EXISTS strategies (
  id SERIAL NOT NULL PRIMARY KEY,
  strategy strategy_enum DEFAULT 'osm',
  identifier VARCHAR(100),
  fk_users INTEGER NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT strategie_user FOREIGN KEY (fk_users)
    REFERENCES users (id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS strategies_uniq ON strategies (identifier, strategy);

CREATE TABLE IF NOT EXISTS places (
  id SERIAL NOT NULL PRIMARY KEY,
  node BIGINT DEFAULT NULL,
  place place_enum DEFAULT 'city',
  last_check TIMESTAMP DEFAULT NULL,
  lat DECIMAL(7, 5) NOT NULL,
  lon DECIMAL(8, 5) NOT NULL,
  name VARCHAR(160) NOT NULL,
  fk_places INTEGER DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT place_place FOREIGN KEY (fk_places)
    REFERENCES places (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS places_name ON places (name varchar_pattern_ops);
CREATE UNIQUE INDEX IF NOT EXISTS places_location ON places (lat, lon);

DROP TRIGGER IF EXISTS places_update_timestamp ON places;
CREATE TRIGGER places_update_timestamp BEFORE UPDATE ON places
  FOR EACH ROW EXECUTE PROCEDURE update_timestamp();

CREATE TABLE IF NOT EXISTS user_places (
  fk_users INTEGER NOT NULL,
  fk_places INTEGER NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT user_place_user FOREIGN KEY (fk_users)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT user_place_place FOREIGN KEY (fk_places)
    REFERENCES places (id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS user_places_uniq ON user_places (fk_users, fk_places);

CREATE TABLE IF NOT EXISTS paths (
  id SERIAL NOT NULL PRIMARY KEY,
  fk_paths INTEGER DEFAULT NULL,
  fk_start INTEGER NOT NULL,
  fk_end INTEGER NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT path_path FOREIGN KEY (fk_paths)
    REFERENCES paths (id) ON DELETE CASCADE,
  CONSTRAINT path_place_start FOREIGN KEY (fk_start)
    REFERENCES places (id) ON DELETE CASCADE,
  CONSTRAINT path_place_end FOREIGN KEY (fk_end)
    REFERENCES places (id) ON DELETE CASCADE
);

DROP TRIGGER IF EXISTS paths_update_timestamp ON paths;
CREATE TRIGGER paths_update_timestamp BEFORE UPDATE ON paths
  FOR EACH ROW EXECUTE PROCEDURE update_timestamp();

CREATE TABLE IF NOT EXISTS user_paths (
  fk_users INTEGER NOT NULL,
  fk_paths INTEGER NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT user_paths_user FOREIGN KEY (fk_users)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT user_paths_path FOREIGN KEY (fk_paths)
    REFERENCES paths (id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS user_paths_uniq ON user_paths (fk_users, fk_paths);

CREATE TABLE IF NOT EXISTS hashes (
  id SERIAL NOT NULL PRIMARY KEY,
  hash VARCHAR(5) COLLATE "C" NOT NULL,
  len SMALLINT NOT NULL DEFAULT 5,
  fk_places INTEGER DEFAULT NULL,
  fk_segments INTEGER DEFAULT NULL
--,  CONSTRAINT hash_place FOREIGN KEY (fk_places)
--    REFERENCES places (id) ON DELETE CASCADE,
--  CONSTRAINT hash_segments FOREIGN KEY (fk_segments)
--    REFERENCES segments (id) ON DELETE CASCASE
);

CREATE TABLE IF NOT EXISTS segments (
  id SERIAL NOT NULL PRIMARY KEY,
  fk_paths INTEGER NOT NULL,
  zoom SMALLINT NOT NULL DEFAULT 18,
  segment VARCHAR(5000) NOT NULL,
  direction SMALLINT NOT NULL DEFAULT 0,
  CONSTRAINT segments_path FOREIGN KEY (fk_paths)
    REFERENCES segments (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS hash_places (
  fk_places INTEGER NOT NULL,
  fk_hashes INTEGER NOT NULL,
  CONSTRAINT hash_places_place FOREIGN KEY (fk_places)
    REFERENCES places (id) ON DELETE CASCADE,
  CONSTRAINT hash_places_hash FOREIGN KEY (fk_hashes)
    REFERENCES hashes (id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS hash_places_uniq ON hash_places (fk_places, fk_hashes);

CREATE TABLE IF NOT EXISTS hash_segments (
  fk_segments INTEGER NOT NULL,
  fk_paths INTEGER NOT NULL,
  fk_hashes INTEGER NOT NULL,
  CONSTRAINT hash_segments_segment FOREIGN KEY (fk_segments)
    REFERENCES segments (id) ON DELETE CASCADE,
  CONSTRAINT hash_segments_path FOREIGN KEY (fk_paths)
    REFERENCES paths (id) ON DELETE CASCADE,
  CONSTRAINT hash_segments_hashes FOREIGN KEY (fk_hashes)
    REFERENCES hashes (id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS hash_segments_uniq ON hash_segments (fk_segments, fk_paths, fk_hashes);

/*
CREATE TABLE IF NOT EXISTS hashes (
  id SERIAL NOT NULL PRIMARY KEY,
  h2 CHAR(2) COLLATE "C" NOT NULL,
  h3 CHAR(3) COLLATE "C" NOT NULL,
  h4 CHAR(4) COLLATE "C" NOT NULL,
  h5 CHAR(5) COLLATE "C" NOT NULL,
  fk_places INTEGER DEFAULT NULL,
  fk_paths INTEGER DEFAULT NULL,
  CONSTRAINT hash_place FOREIGN KEY (fk_places)
    REFERENCES places (id) ON DELETE CASCADE,
  CONSTRAINT hash_paths FOREIGN KEY (fk_paths)
    REFERENCES paths (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS hashes_h2 ON hashes (h2);
CREATE INDEX IF NOT EXISTS hashes_h3 ON hashes (h3);
CREATE INDEX IF NOT EXISTS hashes_h4 ON hashes (h4);
CREATE INDEX IF NOT EXISTS hashes_h5 ON hashes (h5);
*/

CREATE TABLE IF NOT EXISTS reports (
  id SERIAL NOT NULL PRIMARY KEY,
  report report_enum DEFAULT 'path_changed',
  fk_places INTEGER DEFAULT NULL,
  fk_paths INTEGER DEFAULT NULL,
  fk_users INTEGER DEFAULT NULL,
  fk_reports INTEGER DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT report_place FOREIGN KEY (fk_places)
    REFERENCES places (id) ON DELETE CASCADE,
  CONSTRAINT report_path FOREIGN KEY (fk_paths)
    REFERENCES paths (id) ON DELETE CASCADE,
  CONSTRAINT report_user FOREIGN KEY (fk_users)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT report_report FOREIGN KEY (fk_reports)
    REFERENCES reports (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS report_users (
  id SERIAL NOT NULL PRIMARY KEY,
  sent BOOLEAN DEFAULT '0',
  fk_users INTEGER NOT NULL,
  fk_reports INTEGER NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT report_user_user FOREIGN KEY (fk_users)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT report_user_report FOREIGN KEY (fk_reports)
    REFERENCES reports (id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS report_users_uniq ON report_users (fk_users, fk_reports);

DROP TRIGGER IF EXISTS report_users_update_timestamp ON report_users;
CREATE TRIGGER report_users_update_timestamp BEFORE UPDATE ON report_users
  FOR EACH ROW EXECUTE PROCEDURE update_timestamp();

CREATE TABLE IF NOT EXISTS resolutions (
  id SERIAL NOT NULL PRIMARY KEY,
  fk_reports INTEGER NOT NULL,
  fk_users INTEGER NOT NULL,
  resolution TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT resolution_report FOREIGN KEY (fk_reports)
    REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT resolution_user FOREIGN KEY (fk_users)
    REFERENCES users (id) ON DELETE CASCADE
);

DROP TRIGGER IF EXISTS resolutions_update_timestamp ON resolutions;
CREATE TRIGGER resolutions_update_timestamp BEFORE UPDATE ON resolutions
  FOR EACH ROW EXECUTE PROCEDURE update_timestamp();
