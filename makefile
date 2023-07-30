lucillechild.zip: $(shell find "lucillechild")
	rm -f lucillechild.zip
	zip -r lucillechild.zip lucillechild

all:
	lucillechild.zip

clean:
	rm -f lucillechild.zip
