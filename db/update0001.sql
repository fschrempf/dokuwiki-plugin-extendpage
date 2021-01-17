CREATE TABLE assignments_patterns (
    pattern NOT NULL,
    page NOT NULL,
    pos NOT NULL,
    PRIMARY KEY(pattern, page, pos)
);

CREATE TABLE assignments (
    pid NOT NULL,
    page NOT NULL,
    pos NOT NULL,
    assigned BOOLEAN NOT NULL DEFAULT 1,
    PRIMARY KEY(pid, page, pos)
);