package main

import (
	"encoding/json"
	"github.com/rjeczalik/notify"
	"os"
)

type WatchEvent struct {
	Name string `json:"name"`
	Op   string `json:"operation"`
}

var mapping = map[notify.Event]string{
	notify.Create: "create",
	notify.Remove: "remove",
	notify.Rename: "rename",
	notify.Write:  "write",
}

func main() {
	directory := "./..."

	if len(os.Args) > 1 {
		directory = os.Args[1]
	}

	eventChannel := make(chan notify.EventInfo, 10)
	done := make(chan bool)

	go func() {
		defer close(done)

		for {
			event := <-eventChannel
			// serialize event to json
			json, err := json.Marshal(&WatchEvent{
				Name: event.Path(),
				Op:   mapping[event.Event()],
			})

			if err != nil {
				os.Stderr.WriteString(err.Error())
			}

			// send json to stdout
			os.Stdout.Write(json)
			os.Stdout.WriteString("\n")
		}
	}()

	if err := notify.Watch(directory, eventChannel, notify.All); err != nil {
		os.Stderr.WriteString(err.Error())
	}

	defer notify.Stop(eventChannel)

	<-done
}
