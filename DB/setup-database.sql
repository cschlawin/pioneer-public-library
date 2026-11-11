-- DB/setup-database.sql
-- Pioneer Public Library: schema and seed data for member management
-- (UC 10-13), inventory management (UC 14-17), and checkout and return at the
-- staff desk (UC 04, UC 06). Build the database with:
--
--     sqlite3 DB/library.db < DB/setup-database.sql
--
-- or, where the sqlite3 command is not installed:
--
--     php DB/setup.php
--
-- Table and column names follow the Phase 1 ERD (Member, Item, the five
-- media subtypes keyed on Item_ID, Copy, Checkout). Differences from the
-- ERD, kept small on purpose:
--   * Member_ID and Item_ID are TEXT ('M-01045', 'I-04412') so the IDs the
--     pages, wireframes and screenshots show stay the same.
--   * Item carries Is_Deleted for the soft delete (DR 14).
--   * Book carries ISBN, which the add/edit forms collect.
--   * Copy carries Copy_Label ('C-1') and Barcode, which WF 08 shows.
--   * Checkout.Check_Num is TEXT ('L-00005'). Checkout_Emp_ID and
--     Checkin_Emp_ID hold the staff account's username until the Employee
--     table exists, so they carry no foreign key yet.
-- Login accounts, employees, fees and payments are Phase 2 tables and are
-- not created here.
--
-- Every CREATE is IF NOT EXISTS and every seed row is INSERT OR IGNORE, so
-- running the file again only restores seed rows that are missing.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS Member (
    Member_ID     TEXT PRIMARY KEY CHECK (Member_ID GLOB 'M-[0-9][0-9][0-9][0-9][0-9]'),
    FirstName     TEXT NOT NULL,
    LastName      TEXT NOT NULL,
    Email         TEXT NOT NULL COLLATE NOCASE UNIQUE,
    Phone         TEXT NOT NULL DEFAULT '',
    Street        TEXT NOT NULL,
    City          TEXT NOT NULL,
    State         TEXT NOT NULL CHECK (length(State) = 2),
    ZIP           TEXT NOT NULL,
    Start_Date    TEXT NOT NULL,
    Member_Status TEXT NOT NULL DEFAULT 'active'
        CHECK (Member_Status IN ('active', 'inactive'))
);

CREATE TABLE IF NOT EXISTS Item (
    Item_ID        TEXT PRIMARY KEY CHECK (Item_ID GLOB 'I-[0-9][0-9][0-9][0-9][0-9]'),
    Item_Type      TEXT NOT NULL
        CHECK (Item_Type IN ('book', 'music', 'film', 'periodical', 'audiobook')),
    Item_Condition TEXT NOT NULL DEFAULT 'good'
        CHECK (Item_Condition IN ('new', 'good', 'fair', 'poor')),
    Is_Deleted     INTEGER NOT NULL DEFAULT 0 CHECK (Is_Deleted IN (0, 1))
);

CREATE TABLE IF NOT EXISTS Book (
    Item_ID      TEXT PRIMARY KEY REFERENCES Item (Item_ID) ON DELETE CASCADE,
    Book_Title   TEXT NOT NULL,
    Author_Name  TEXT NOT NULL,
    Publisher    TEXT,
    Publish_Date TEXT,
    Genre        TEXT,
    ISBN         TEXT
);

CREATE TABLE IF NOT EXISTS Music (
    Item_ID     TEXT PRIMARY KEY REFERENCES Item (Item_ID) ON DELETE CASCADE,
    Album_Title TEXT NOT NULL,
    Artist      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS Film (
    Item_ID       TEXT PRIMARY KEY REFERENCES Item (Item_ID) ON DELETE CASCADE,
    Film_Title    TEXT NOT NULL,
    Director      TEXT,
    Year_Released INTEGER,
    Genre         TEXT
);

CREATE TABLE IF NOT EXISTS Periodical (
    Item_ID         TEXT PRIMARY KEY REFERENCES Item (Item_ID) ON DELETE CASCADE,
    Periodical_Name TEXT NOT NULL,
    Publish_Date    TEXT,
    Issue_Number    TEXT
);

CREATE TABLE IF NOT EXISTS Audiobook (
    Item_ID         TEXT PRIMARY KEY REFERENCES Item (Item_ID) ON DELETE CASCADE,
    Audiobook_Title TEXT NOT NULL,
    Author_Name     TEXT NOT NULL,
    Publisher       TEXT,
    Publish_Date    TEXT,
    Genre           TEXT
);

-- One row per physical copy. Exactly one status at a time (BR 08).
CREATE TABLE IF NOT EXISTS Copy (
    Copy_ID        INTEGER PRIMARY KEY,
    Item_ID        TEXT NOT NULL REFERENCES Item (Item_ID),
    Copy_Label     TEXT NOT NULL,
    Barcode        TEXT NOT NULL UNIQUE,
    Copy_Condition TEXT NOT NULL DEFAULT 'good'
        CHECK (Copy_Condition IN ('new', 'good', 'fair', 'poor')),
    Copy_Status    TEXT NOT NULL DEFAULT 'available'
        CHECK (Copy_Status IN ('available', 'checked out', 'missing')),
    UNIQUE (Item_ID, Copy_Label)
);

CREATE INDEX IF NOT EXISTS idx_copy_item ON Copy (Item_ID);

-- One row per loan of one copy (DR 06). Check_In_Date stays NULL while the
-- copy is out. The due date is 30 calendar days after checkout (BR 01).
CREATE TABLE IF NOT EXISTS Checkout (
    Check_Num       TEXT PRIMARY KEY CHECK (Check_Num GLOB 'L-[0-9][0-9][0-9][0-9][0-9]'),
    Copy_ID         INTEGER NOT NULL REFERENCES Copy (Copy_ID),
    Member_ID       TEXT NOT NULL REFERENCES Member (Member_ID),
    Checkout_Emp_ID TEXT NOT NULL,
    Checkin_Emp_ID  TEXT,
    Check_Out_Date  TEXT NOT NULL CHECK (Check_Out_Date = date(Check_Out_Date)),
    Check_Due_Date  TEXT NOT NULL CHECK (Check_Due_Date = date(Check_Due_Date)
                                         AND Check_Due_Date >= Check_Out_Date),
    Check_In_Date   TEXT CHECK (Check_In_Date IS NULL
                                OR (Check_In_Date = date(Check_In_Date)
                                    AND Check_In_Date >= Check_Out_Date)),
    CHECK ((Check_In_Date IS NULL) = (Checkin_Emp_ID IS NULL))
);

-- A copy can be on at most one open loan at a time (BR 04).
CREATE UNIQUE INDEX IF NOT EXISTS ux_checkout_open_copy ON Checkout (Copy_ID)
    WHERE Check_In_Date IS NULL;
CREATE INDEX IF NOT EXISTS idx_checkout_member ON Checkout (Member_ID);

-- Members: the WF 03 / WF 05 seed rows, same IDs as the Phase 1 pages.
INSERT OR IGNORE INTO Member (Member_ID, FirstName, LastName, Email, Phone, Street, City, State, ZIP, Start_Date, Member_Status) VALUES ('M-01045', 'Thao', 'Nguyen', 't.nguyen@example.org', '801-555-0142', '88 Emigration Ct', 'Salt Lake City', 'UT', '84108', '2021-03-04', 'active');
INSERT OR IGNORE INTO Member (Member_ID, FirstName, LastName, Email, Phone, Street, City, State, ZIP, Start_Date, Member_Status) VALUES ('M-00778', 'Daniel', 'Okafor', 'd.okafor@example.org', '801-555-0187', '412 E 300 S', 'Salt Lake City', 'UT', '84111', '2019-08-19', 'active');
INSERT OR IGNORE INTO Member (Member_ID, FirstName, LastName, Email, Phone, Street, City, State, ZIP, Start_Date, Member_Status) VALUES ('M-00312', 'Renata', 'Silva', 'r.silva@example.org', '801-555-0113', '77 Harvard Ave', 'Salt Lake City', 'UT', '84102', '2017-01-23', 'inactive');
INSERT OR IGNORE INTO Member (Member_ID, FirstName, LastName, Email, Phone, Street, City, State, ZIP, Start_Date, Member_Status) VALUES ('M-00087', 'Amy', 'Whitfield', 'a.whitfield@example.org', '801-555-0155', '1901 S Windsor St', 'Salt Lake City', 'UT', '84105', '2015-06-08', 'active');
INSERT OR IGNORE INTO Member (Member_ID, FirstName, LastName, Email, Phone, Street, City, State, ZIP, Start_Date, Member_Status) VALUES ('M-01210', 'Ellis', 'Brooks', 'e.brooks@example.org', '801-555-0121', '350 N Center St', 'Lehi', 'UT', '84043', '2024-02-14', 'active');
INSERT OR IGNORE INTO Member (Member_ID, FirstName, LastName, Email, Phone, Street, City, State, ZIP, Start_Date, Member_Status) VALUES ('M-00566', 'Luis', 'Ibarra', 'l.ibarra@example.org', '801-555-0198', '620 W 400 N', 'Salt Lake City', 'UT', '84116', '2018-11-05', 'inactive');

-- Inventory: the WF 06 items and WF 08 copies, same IDs and barcodes.
INSERT OR IGNORE INTO Item (Item_ID, Item_Type, Item_Condition, Is_Deleted) VALUES ('I-04412', 'book', 'good', 0);
INSERT OR IGNORE INTO Book (Item_ID, Book_Title, Author_Name, Publisher, Publish_Date, Genre, ISBN) VALUES ('I-04412', 'The Overstory', 'Richard Powers', 'W. W. Norton', '2018-04-03', 'Literary fiction', '978-0393635522');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (1, 'I-04412', 'C-1', '30111 00482', 'good', 'available');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (2, 'I-04412', 'C-2', '30111 00483', 'good', 'checked out');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (3, 'I-04412', 'C-3', '30111 00484', 'fair', 'available');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (4, 'I-04412', 'C-4', '30111 00485', 'good', 'available');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (5, 'I-04412', 'C-5', '30111 00486', 'poor', 'missing');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (6, 'I-04412', 'C-6', '30111 00487', 'good', 'checked out');

INSERT OR IGNORE INTO Item (Item_ID, Item_Type, Item_Condition, Is_Deleted) VALUES ('I-03881', 'film', 'good', 0);
INSERT OR IGNORE INTO Film (Item_ID, Film_Title, Director, Year_Released, Genre) VALUES ('I-03881', 'Arrival', 'Denis Villeneuve', '2016', 'Science fiction');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (7, 'I-03881', 'C-1', '30111 00311', 'good', 'available');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (8, 'I-03881', 'C-2', '30111 00312', 'good', 'checked out');

INSERT OR IGNORE INTO Item (Item_ID, Item_Type, Item_Condition, Is_Deleted) VALUES ('I-05102', 'music', 'good', 0);
INSERT OR IGNORE INTO Music (Item_ID, Album_Title, Artist) VALUES ('I-05102', 'Blue Train', 'John Coltrane');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (9, 'I-05102', 'C-1', '30111 00550', 'good', 'available');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (10, 'I-05102', 'C-2', '30111 00551', 'good', 'available');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (11, 'I-05102', 'C-3', '30111 00552', 'fair', 'available');

INSERT OR IGNORE INTO Item (Item_ID, Item_Type, Item_Condition, Is_Deleted) VALUES ('I-04477', 'periodical', 'good', 0);
INSERT OR IGNORE INTO Periodical (Item_ID, Periodical_Name, Publish_Date, Issue_Number) VALUES ('I-04477', 'Wired, Nov 2026', '2026-11-01', 'November 2026');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (12, 'I-04477', 'C-1', '30111 00777', 'good', 'checked out');

INSERT OR IGNORE INTO Item (Item_ID, Item_Type, Item_Condition, Is_Deleted) VALUES ('I-05220', 'audiobook', 'good', 0);
INSERT OR IGNORE INTO Audiobook (Item_ID, Audiobook_Title, Author_Name, Publisher, Publish_Date, Genre) VALUES ('I-05220', 'Braiding Sweetgrass', 'Robin Wall Kimmerer', 'Milkweed Editions', '2013-09-01', 'Nature writing');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (13, 'I-05220', 'C-1', '30111 00901', 'good', 'available');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (14, 'I-05220', 'C-2', '30111 00902', 'new', 'checked out');

INSERT OR IGNORE INTO Item (Item_ID, Item_Type, Item_Condition, Is_Deleted) VALUES ('I-04099', 'book', 'good', 0);
INSERT OR IGNORE INTO Book (Item_ID, Book_Title, Author_Name, Publisher, Publish_Date, Genre, ISBN) VALUES ('I-04099', 'Klara and the Sun', 'Kazuo Ishiguro', 'Knopf', '2021-03-02', 'Literary fiction', '978-0593318171');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (15, 'I-04099', 'C-1', '30111 00810', 'good', 'available');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (16, 'I-04099', 'C-2', '30111 00811', 'fair', 'missing');

INSERT OR IGNORE INTO Item (Item_ID, Item_Type, Item_Condition, Is_Deleted) VALUES ('I-03501', 'book', 'good', 0);
INSERT OR IGNORE INTO Book (Item_ID, Book_Title, Author_Name, Publisher, Publish_Date, Genre, ISBN) VALUES ('I-03501', 'Severance', 'Ling Ma', 'Farrar, Straus and Giroux', '2018-08-14', 'Literary fiction', '978-0374261597');
INSERT OR IGNORE INTO Copy (Copy_ID, Item_ID, Copy_Label, Barcode, Copy_Condition, Copy_Status) VALUES (17, 'I-03501', 'C-1', '30111 00601', 'good', 'available');

-- Checkouts: returned loans behind the WF 05 history and one open loan for
-- every copy the inventory seed marks checked out.
INSERT OR IGNORE INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Checkin_Emp_ID, Check_Out_Date, Check_Due_Date, Check_In_Date) VALUES ('L-00001', 15, 'M-01045', 'bsmith', 'bsmith', '2026-07-09', '2026-08-08', '2026-08-11');
INSERT OR IGNORE INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Checkin_Emp_ID, Check_Out_Date, Check_Due_Date, Check_In_Date) VALUES ('L-00002', 13, 'M-01045', 'bsmith', 'bsmith', '2026-08-22', '2026-09-21', '2026-09-19');
INSERT OR IGNORE INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Checkin_Emp_ID, Check_Out_Date, Check_Due_Date, Check_In_Date) VALUES ('L-00003', 9, 'M-00778', 'bsmith', 'bsmith', '2026-08-30', '2026-09-29', '2026-10-02');
INSERT OR IGNORE INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Checkin_Emp_ID, Check_Out_Date, Check_Due_Date, Check_In_Date) VALUES ('L-00004', 17, 'M-01045', 'bsmith', 'bsmith', '2026-09-14', '2026-10-14', '2026-10-18');
INSERT OR IGNORE INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Checkin_Emp_ID, Check_Out_Date, Check_Due_Date, Check_In_Date) VALUES ('L-00005', 2, 'M-01045', 'bsmith', NULL, '2026-10-02', '2026-11-01', NULL);
INSERT OR IGNORE INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Checkin_Emp_ID, Check_Out_Date, Check_Due_Date, Check_In_Date) VALUES ('L-00006', 8, 'M-00778', 'bsmith', NULL, '2026-10-11', '2026-11-10', NULL);
INSERT OR IGNORE INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Checkin_Emp_ID, Check_Out_Date, Check_Due_Date, Check_In_Date) VALUES ('L-00007', 12, 'M-00087', 'bsmith', NULL, '2026-10-20', '2026-11-19', NULL);
INSERT OR IGNORE INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Checkin_Emp_ID, Check_Out_Date, Check_Due_Date, Check_In_Date) VALUES ('L-00008', 6, 'M-01210', 'bsmith', NULL, '2026-10-25', '2026-11-24', NULL);
INSERT OR IGNORE INTO Checkout (Check_Num, Copy_ID, Member_ID, Checkout_Emp_ID, Checkin_Emp_ID, Check_Out_Date, Check_Due_Date, Check_In_Date) VALUES ('L-00009', 14, 'M-00087', 'bsmith', NULL, '2026-11-03', '2026-12-03', NULL);
