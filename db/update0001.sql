CREATE TABLE assignments_patterns (
    id INTEGER PRIMARY KEY,
    pattern NOT NULL,
    page NOT NULL,
    pos NOT NULL
);

CREATE TABLE assignments (
    pid NOT NULL,
    pattern_id INTEGER NOT NULL,
    assigned BOOLEAN NOT NULL DEFAULT 1,
    PRIMARY KEY(pid, pattern_id)
);