package main

import (
	_ "embed"
	"errors"
	"fmt"
	"html/template"
	"io"
	"math"
	"net/http"
	"os"
	"strings"
	"time"

	"github.com/google/uuid"
	"gorm.io/driver/postgres"
	"gorm.io/gorm"
)

//go:embed index.html
var index string

//go:embed players.html
var players string

//go:embed spawn.webp
var spawn []byte

//go:embed style.css
var styles string

//go:embed gallery.html
var gallery string

//go:embed galleryAdmin.html
var galleryAdmin string

type Player struct {
	gorm.Model

	Name     string
	Playtime int64
}

type PlayerTimeTrack struct {
	Player          string `gorm:"primaryKey"`
	PlayerStartTime time.Time
}

type GalleryItem struct {
	gorm.Model

	FileName    string
	Description string
	UserName    string
	Approved    bool
}

func ParseTime(secs int) string {
	var months int
	var weeks int
	var days int
	var hours int
	var minutes int

	months = int(math.Floor(float64(secs) / 2419200))
	secs -= months * 2419200

	weeks = int(math.Floor(float64(secs) / 604800))
	secs -= weeks * 604800

	days = int(math.Floor(float64(secs) / 86400))
	secs -= days * 86400

	hours = int(math.Floor(float64(secs) / 3600))
	secs -= hours * 3600

	minutes = int(math.Floor(float64(secs) / 60))

	if months > 0 {
		return fmt.Sprintf("%dmo %dw %dd %dh %dm", months, weeks, days, hours, minutes)
	} else if weeks > 0 {
		return fmt.Sprintf("%dw %dd %dh %dm", weeks, days, hours, minutes)
	} else if days > 0 {
		return fmt.Sprintf("%dd %dh %dm", days, hours, minutes)
	} else if hours > 0 {
		return fmt.Sprintf("%dh %dm", hours, minutes)
	} else {
		return fmt.Sprintf("%dm", minutes)
	}
}

func HandleIndex(w http.ResponseWriter, r *http.Request) {
	w.Write([]byte(index))
}

func HandlePlayers(w http.ResponseWriter, r *http.Request) {
	tmpl, err := template.New("players").Parse(players)
	if err != nil {
		w.WriteHeader(500)
		w.Write([]byte("An internal server error occured"))
		return
	}

	type Pl struct {
		Name     string
		Playtime string
	}

	var onlinePlayers []Pl
	var players []Pl
	var onlinePlayersDb []Player
	db.Find(&onlinePlayersDb)
	for i := 0; i < len(onlinePlayersDb); i++ {
		var additionalPlaytime = 0
		var additionalPlaytimeDb PlayerTimeTrack // PlayerTimeTrack will always have record of online player
		err := db.Where(&PlayerTimeTrack{Player: onlinePlayersDb[i].Name}).First(&additionalPlaytimeDb)
		if !errors.Is(err.Error, gorm.ErrRecordNotFound) {
			additionalPlaytime = int(time.Since(additionalPlaytimeDb.PlayerStartTime).Seconds())
			onlinePlayers = append(onlinePlayers, Pl{
				Name:     onlinePlayersDb[i].Name,
				Playtime: ParseTime(int(onlinePlayersDb[i].Playtime) + additionalPlaytime),
			})
		}

		players = append(players, Pl{
			Name:     onlinePlayersDb[i].Name,
			Playtime: ParseTime(int(onlinePlayersDb[i].Playtime)),
		})
	}

	tmpl.ExecuteTemplate(w, "players", struct {
		OnlinePlayers  []Pl
		Players        []Pl
		IsOnlinePlayer bool
	}{
		OnlinePlayers:  onlinePlayers,
		Players:        players,
		IsOnlinePlayer: len(onlinePlayers) > 0,
	})
}

func HandleGallery(w http.ResponseWriter, r *http.Request) {
	if r.Method == "POST" {
		err := r.ParseMultipartForm(10 << 20)
		if err != nil {
			err = fmt.Errorf("parsing multipart form failed: %w", err)
			fmt.Fprint(w, err)
			return
		}

		// Validate the ign
		name := r.FormValue("ign")
		if len(name) <= 2 || len(name) >= 16 {
			err = errors.New("name must be between 2 and 16 characters")
			err = fmt.Errorf("failed submitting: %w", err)
			fmt.Fprint(w, err)
			return
		}

		// Validate the description
		description := r.FormValue("description")
		if len(description) <= 10 || len(description) >= 2000 {
			err = errors.New("description must be between 10 and 2000 characters")
			err = fmt.Errorf("failed submitting: %w", err)
			fmt.Fprint(w, err)
			return
		}

		// Get file
		file, handler, err := r.FormFile("image")
		if err != nil {
			err = fmt.Errorf("failed parsing file: %w", err)
			fmt.Fprint(w, err)
			return
		}
		defer file.Close()

		// Read file
		fileData, err := io.ReadAll(file)
		if err != nil {
			err = fmt.Errorf("error reading file: %w", err)
			fmt.Fprint(w, err)
			return
		}

		// Content Type
		contentType := http.DetectContentType(fileData)
		if !strings.HasPrefix(contentType, "image/") {
			err = errors.New("invalid content-type")
			err = fmt.Errorf("failed verifying upload: %w", err)
			fmt.Fprint(w, err)
			return
		}

		// Determine extension
		fileNameSplit := strings.Split(handler.Filename, ".")
		ext := fileNameSplit[len(fileNameSplit)-1]

		// Write
		dst, err := os.Create(fmt.Sprintf("gallery/%s.%s", uuid.New().String(), ext))
		if err != nil {
			err = fmt.Errorf("failed creating file: %w", err)
			fmt.Print(w, err)
			return
		}

		_, err = dst.Write(fileData)
		if err != nil {
			err = fmt.Errorf("failed writing data: %w", err)
			fmt.Print(w, err)
			return
		}

		defer dst.Close()

		// DB insert

		http.Redirect(w, r, "/gallery", http.StatusSeeOther)
		return
	}

	tmpl, err := template.New("gallery").Parse(gallery)
	if err != nil {
		w.WriteHeader(500)
		w.Write([]byte("An internal server error occured"))
		return
	}

	var galleryItemsDb []GalleryItem
	var data []struct {
		Name        string
		Url         string
		Description string
	}
	db.Where(&GalleryItem{Approved: true}).Find(&galleryItemsDb)
	for i := 0; i < len(data); i++ {
		data = append(data, struct {
			Name        string
			Url         string
			Description string
		}{
			Name:        galleryItemsDb[i].UserName,
			Url:         fmt.Sprintf("/gallery/%s", galleryItemsDb[i].FileName),
			Description: galleryItemsDb[i].Description,
		})
	}

	tmpl.Execute(w, struct {
		Items []struct {
			Name        string
			Url         string
			Description string
		}
	}{
		Items: data,
	})
}

func HandleGalleryFiles(w http.ResponseWriter, r *http.Request) {

	args := strings.Split(r.URL.Path, "/")
	fileName := args[len(args)-1]

	dst, err := os.Open(fmt.Sprintf("gallery/%s", fileName))
	if err != nil {
		http.NotFound(w, r)
		return
	}
	defer dst.Close()

	read, err := io.ReadAll(dst)
	if err != nil {
		err = fmt.Errorf("unable to read file: %w", err)
		fmt.Fprint(w, err)
	}

	contentType := http.DetectContentType(read)
	r.Header.Set("Content-Type", contentType)

	w.Write(read)
}

func HandleGalleryAdmin(w http.ResponseWriter, r *http.Request) {
	if r.Method == "POST" {
		err := r.ParseForm()
		if err != nil {
			err = fmt.Errorf("unable to parse form data: %w", err)
			fmt.Fprint(w, err)
			return
		}

	}

	tmpl, err := template.New("galleryAdmin").Parse(galleryAdmin)
	if err != nil {
		err = fmt.Errorf("failed loading template: %w", err)
		fmt.Fprint(w, err)
		return
	}

	var galleryItem GalleryItem
	transact := db.First(&galleryItem)
	if errors.Is(transact.Error, gorm.ErrRecordNotFound) {
		tmpl.Execute(w, struct {
			NotFound bool
		}{
			NotFound: true,
		})

		return
	}

	tmpl.Execute(w, struct {
		Id          int
		Name        string
		Description string
		Url         string
	}{
		Id:          int(galleryItem.ID),
		Name:        galleryItem.UserName,
		Description: galleryItem.Description,
		Url:         fmt.Sprintf("/gallery/%s", galleryItem.FileName),
	})
}

func HandleSpawnImage(w http.ResponseWriter, r *http.Request) {
	w.Write(spawn)
}

func HandleStyles(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/css")
	w.Write([]byte(styles))
}

var db *gorm.DB

func main() {
	dbHost := os.Getenv("DB_POSTGRES_HOST")
	dbUser := os.Getenv("DB_POSTGRES_USER")
	dbPassword := os.Getenv("DB_POSTGRES_PASSWORD")
	dbName := os.Getenv("DB_POSTGRES_NAME")
	dbPort := os.Getenv("DB_POSTGRES_PORT")
	if strings.TrimSpace(dbPort) == "" {
		dbPort = "5432"
	}

	dsn := fmt.Sprintf("host=%s user=%s password=%s dbname=%s port=%s sslmode=disable", dbHost, dbUser, dbPassword, dbName, dbPort)
	db2, err := gorm.Open(postgres.Open(dsn), &gorm.Config{})
	db = db2

	db.AutoMigrate(&Player{})
	db.AutoMigrate(&PlayerTimeTrack{})
	db.AutoMigrate(&GalleryItem{})

	// Home page
	http.HandleFunc("/", http.HandlerFunc(HandleIndex))

	// Players
	http.HandleFunc("/players", http.HandlerFunc(HandlePlayers))

	// Gallery
	http.HandleFunc("/gallery", http.HandlerFunc(HandleGallery))
	http.Handle("/gallery/", http.HandlerFunc(HandleGalleryFiles))

	// Other
	http.HandleFunc("/spawn.webp", http.HandlerFunc(HandleSpawnImage))
	http.HandleFunc("/style.css", http.HandlerFunc(HandleStyles))

	println("Starting website")
	err = http.ListenAndServe(":8080", nil)
	if err != nil {
		panic(err)
	}
}
