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
	"strconv"
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

		uuid := uuid.New().String()

		// Write
		dst, err := os.Create(fmt.Sprintf("gallery/%s.%s", uuid, ext))
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
		db.Create(&GalleryItem{
			FileName:    fmt.Sprintf("gallery/%s.%s", uuid, ext),
			Description: description,
			UserName:    name,
			Approved:    false,
		})

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
	db.Where(&GalleryItem{Approved: true}).Order("created_at DESC").Find(&galleryItemsDb)
	for i := 0; i < len(galleryItemsDb); i++ {
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
	user, pass, ok := r.BasicAuth()
	if !ok || user != os.Getenv("ADMIN_USER") || pass != os.Getenv("ADMIN_PASS") {
		w.Header().Set("WWW-Authenticate", `Basic realm="restricted", charset="UTF-8"`)
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	if r.Method == "POST" {
		err := r.ParseForm()
		if err != nil {
			err = fmt.Errorf("unable to parse form data: %w", err)
			fmt.Fprint(w, err)
			return
		}

		id := -1
		if parsedId, err := strconv.Atoi(r.FormValue("id")); err == nil {
			id = parsedId
		} else {
			err := errors.New("ID is not a number")
			err = fmt.Errorf("failed to parse data: %w", err)
			fmt.Fprint(w, err)
			return
		}

		if del := r.FormValue("delete"); del == "on" {
			// Delete row, file and exit

			var galleryItem GalleryItem
			tra := db.Where("id = ?", id).First(&galleryItem)
			if errors.Is(tra.Error, gorm.ErrRecordNotFound) {
				err = fmt.Errorf("failed to update record: %w", err)
				fmt.Fprint(w, err)
				return
			}

			err = os.Remove(galleryItem.FileName)
			if err != nil {
				err = fmt.Errorf("failed to delete file: %w", err)
				fmt.Fprint(w, err)
				return
			}

			db.Delete(&galleryItem)

			http.Redirect(w, r, "/admin/gallery", http.StatusSeeOther)
			return
		}

		name := r.FormValue("name")
		if len(name) < 2 || len(name) > 16 {
			err := errors.New("name is out of range of 2;16")
			err = fmt.Errorf("failed to parse data: %w", err)
			fmt.Fprint(w, err)
			return
		}

		description := r.FormValue("description")
		if len(description) < 10 || len(description) > 2000 {
			err := errors.New("description is out of range of 10;2000")
			err = fmt.Errorf("failed to parse data: %w", err)
			fmt.Fprint(w, err)
			return
		}

		approved := r.FormValue("approved") == "on"

		fmt.Println("Approved is ", approved)

		var galleryItem GalleryItem
		tra := db.Where("id = ?", id).First(&galleryItem)
		if errors.Is(tra.Error, gorm.ErrRecordNotFound) {
			err = fmt.Errorf("failed to update record: %w", err)
			fmt.Fprint(w, err)
			return
		}

		galleryItem.UserName = name
		galleryItem.Description = description
		galleryItem.Approved = approved

		db.Save(&galleryItem)

		http.Redirect(w, r, "/admin/gallery", http.StatusSeeOther)
		return
	}

	tmpl, err := template.New("galleryAdmin").Parse(galleryAdmin)
	if err != nil {
		err = fmt.Errorf("failed loading template: %w", err)
		fmt.Fprint(w, err)
		return
	}

	var galleryItemsDb []GalleryItem
	db.Order("created_at DESC").Find(&galleryItemsDb)
	var galleryItems []struct {
		Id          int
		Url         string
		Name        string
		Description string
		Approved    bool
	}

	for i := 0; i < len(galleryItemsDb); i++ {

		galleryItems = append(galleryItems, struct {
			Id          int
			Url         string
			Name        string
			Description string
			Approved    bool
		}{
			Id:          int(galleryItemsDb[i].ID),
			Url:         fmt.Sprintf("/gallery/%s", galleryItemsDb[i].FileName),
			Name:        galleryItemsDb[i].UserName,
			Description: galleryItemsDb[i].Description,
			Approved:    galleryItemsDb[i].Approved,
		})
	}

	tmpl.Execute(w, struct {
		Items []struct {
			Id          int
			Url         string
			Name        string
			Description string
			Approved    bool
		}
	}{
		Items: galleryItems,
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
	http.HandleFunc("/admin/gallery", http.HandlerFunc(HandleGalleryAdmin))

	// Other
	http.HandleFunc("/spawn.webp", http.HandlerFunc(HandleSpawnImage))
	http.HandleFunc("/style.css", http.HandlerFunc(HandleStyles))

	println("Starting website")
	err = http.ListenAndServe(":8080", nil)
	if err != nil {
		panic(err)
	}
}
